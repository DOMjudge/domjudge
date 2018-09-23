<?php

namespace LegacyBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface as Container;

use DOMJudgeBundle\Utils\Utils;

/**
 * @Route(service="legacy.controller.fallback")
 */
class FallbackController extends Controller
{
    private $webDir;

    public function __construct($webDir, Container $container)
    {
        $this->webDir = $webDir;
        $this->setContainer($container);
    }

    public function fallback(Request $request, $path)
    {
        if ($this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            $user = $this->get('security.token_storage')->getToken()->getUser();
            $user->setLastLogin(Utils::now());
            $user->setLastIpAddress(@$_SERVER['REMOTE_ADDR']);
            $this->getDoctrine()->getManager()->flush();

            $_SESSION['username'] = $user->getUsername();
        }


        $thefile = realpath($this->webDir . $request->getPathInfo());
        // API is handled separately, current default is v4.
        $apiPaths = array(
            'api/v3/' => '/api/v3/index.php',
            'api/v4/' => '/api/v4/index.php',
            'api/'    => '/api/v4/index.php',
        );
        $apiMatch = false;
        $exactApiMatch = false;
        foreach ($apiPaths as $apiPath => $apiRedirect) {
            if ($apiPath === $path) {
                $exactApiMatch = true;
            }
            if (substr($path, 0, strlen($apiPath)) == $apiPath) {
                $_SERVER['PHP_SELF'] = 'index.php';
                $thefile = realpath($this->webDir . $apiRedirect);
                $_SERVER['PATH_INFO'] = substr($path, strlen($apiPath));
                $apiMatch = true;
                break;
            }
        }

        if ($request->server->has('REQUEST_URI')) {
            $_SERVER['REQUEST_URI'] = $request->server->get('REQUEST_URI');
        }

        if ($apiMatch) {
            if (!$exactApiMatch) {
                $request->setRequestFormat('json');
            }
        } else {
            $_SERVER['PHP_SELF'] = basename($path);
            $_SERVER['SCRIPT_NAME'] = basename($path);// This is used in a few scripts to set refererrer
            if (is_dir($thefile)) {
                $thefile = realpath($thefile . "/index.php");
                $_SERVER['PHP_SELF'] = "index.php";

                // Make sure it ends with a trailing slash, otherwise redirect
                $pathInfo = $request->getPathInfo();
                $requestUri = $request->getRequestUri();
                if (rtrim($pathInfo, ' /') == $pathInfo) {
                    $url = str_replace($pathInfo, $pathInfo . '/', $requestUri);
                    return $this->redirect($url, 301);
                }
            }
        }
        if (!file_exists($thefile)) {
            return Response::create('Not found.', 404);
        }
        chdir(dirname($thefile));
        ob_start();
        global $G_SYMFONY;
        $G_SYMFONY = $this->container->get('domjudge.domjudge');
        require($thefile);

        $http_response_code = http_response_code();
        if ($http_response_code === false) {
            // When called from phpunit, the response is not set,
            // which would break the following Response::create call.
            $http_response_code = 200;
        }
        $response = Response::create(ob_get_clean(), $http_response_code);

        // Headers may already have been sent on pages with streaming output.
        if (!headers_sent()) {
            $headers = headers_list();
            header_remove();
            foreach ($headers as $header) {
                $pieces = explode(':', $header);
                $headerName = array_shift($pieces);
                $response->headers->set($headerName, trim(implode(':', $pieces)), false);
            }
        }

        if (!$response->headers->has('Content-Type')) {
            $contentType = mime_content_type($thefile);
            if ($contentType !== 'text/x-php') {
                $response->headers->set('Content-Type', $contentType);
            }
        }

        return $response;
    }
}
