<?php

namespace LegacyBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="legacy.controller.fallback")
 */
class FallbackController extends Controller
{
    private $webDir;

    public function __construct($webDir)
    {
        $this->webDir = $webDir;
    }

    public function fallback(Request $request, $path)
    {
      $thefile = realpath($this->webDir . $request->getPathInfo());
      // API is handled separately(always by api/index.php)
      if (substr($path, 0, 7 ) == "api/v3/") {
          $_SERVER['PHP_SELF'] = 'index.php';
          $thefile = realpath($this->webDir . "/api/v3/index.php");
          $_SERVER['PATH_INFO'] = substr($path,7);
      } else {
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
