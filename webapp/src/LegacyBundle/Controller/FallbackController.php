<?php

namespace LegacyBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface as Container;

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
			$_SESSION['username'] = $this->get('security.token_storage')->getToken()->getUser()->getUsername();
		}


		$thefile = realpath($this->webDir . $request->getPathInfo());
		// API is handled separately, current default is v4.
		$apiPaths = array(
			'api/v3/' => '/api/v3/index.php',
			'api/v4/' => '/api/v4/index.php',
			'api/'    => '/api/v4/index.php',
		);
		$apiMatch = FALSE;
		foreach ( $apiPaths as $apiPath => $apiRedirect ) {
			if (substr($path, 0, strlen($apiPath)) == $apiPath) {
				$_SERVER['PHP_SELF'] = 'index.php';
				$thefile = realpath($this->webDir . $apiRedirect);
				$_SERVER['PATH_INFO'] = substr($path, strlen($apiPath));
				$apiMatch = TRUE;
				break;
			}
		}
		if ( $apiMatch ) {
			$request->setRequestFormat('json');
		}	else {
			$_SERVER['PHP_SELF'] = basename($path);
			$_SERVER['SCRIPT_NAME'] = basename($path);// This is used in a few scripts to set refererrer
			if (is_dir($thefile)) {
				$thefile = realpath($thefile . "/index.php");
				$_SERVER['PHP_SELF'] = "index.php";

				// Make sure it ends with a trailing slash, otherwise redirect
				$pathInfo = $request->getPathInfo();
				$requestUri = $request->getRequestUri();
				if (rtrim($pathInfo, ' /') == $pathInfo ) {
					$url = str_replace($pathInfo, $pathInfo . '/', $requestUri);
					return $this->redirect($url, 301);
				}
			}
		}
		if (file_exists($thefile)) {
			chdir(dirname($thefile));
		}
		ob_start();
		global $G_SYMFONY;
		$G_SYMFONY = $this->container->get('domjudge.domjudge');
		require($thefile);
		$headers = headers_list();
		header_remove();

		$response = Response::create(ob_get_clean(), http_response_code());
		foreach ($headers as $header) {
			$pieces = explode(':', $header);
			$headerName = array_shift($pieces);
			$response->headers->set($headerName, trim(implode(':', $pieces)), false);
		}

		return $response;
	}
}
