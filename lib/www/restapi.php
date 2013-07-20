<?php

/**
 * Functions for providing a REST API
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

define('BAD_REQUEST', '400 Bad Request');
define('METHOD_NOT_ALLOWED', '405 Method Not Allowed');
define('INTERNAL_SERVER_ERROR', '500 Internal Server Error');

class RestApi {
	private $apiFunctions = array();

	/**
	 * Add a function to the list of functions that this API supports.
	 *
	 * Arguments:
	 * $httpMethod    Currently only GET is supported.
	 * $name          Name of the function.
	 * $callback      Callback to the actual implementation of this function.
	 * $docs          Documentation for this function.
	 * $optArgs       List of optional arguments.
	 * $exArgs        Example usage of arguments
	 */
	public function provideFunction($httpMethod, $name, $callback, $docs = '',
	                                $optArgs = array(), $exArgs = array())
	{
		if ( $httpMethod != 'GET' && $httpMethod != 'POST' && $httpMethod != 'PUT' ) {
			$this->createError("Only get/post/put methods supported.", INTERNAL_SERVER_ERROR);
		}
		if ( array_key_exists($name . '#' . $httpMethod, $this->apiFunctions) ) {
			$this->createError("Multiple definitions of " . $name . " for " . $httpMethod . ".", INTERNAL_SERVER_ERROR);
		}
		$this->apiFunctions[$name . '#' . $httpMethod] = array("callback" => $callback,
		                                   "optArgs" => $optArgs,
		                                   "docs" => $docs,
		                                   "exArgs" => $exArgs);
	}

	/**
	 * Provide the actual API
	 */
	public function provideApi()
	{
		if ( !isset($_GET['handler']) ) {
			$this->createError("Handler not set.", INTERNAL_SERVER_ERROR);
		}

		if ( $_SERVER['REQUEST_METHOD'] != 'GET' && $_SERVER['REQUEST_METHOD'] != 'POST' && $_SERVER['REQUEST_METHOD'] != 'PUT' ) {
			$this->createError("Only get/post/put methods supported.", METHOD_NOT_ALLOWED);
		}

		$handler = $_GET['handler'];
		unset($_GET['handler']);
		if ( empty($handler) ) {
			$this->showDocs();
		} else {
			if ( $_SERVER['REQUEST_METHOD'] == 'GET' ) {
				$this->callFunction($handler, $_GET);
			} else if ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {
				$this->callFunction($handler, $_POST);
			} else if ( $_SERVER['REQUEST_METHOD'] == 'PUT' ) {
				parse_str(file_get_contents('php://input'), $_PUT);
				$this->callFunction($handler, $_PUT);
			}
		}
	}

	/**
	 * Call an API function
	 */
	public function callFunction($name, $arguments)
	{
		if ( $_SERVER['REQUEST_METHOD'] == 'PUT' ) {
			list($name, $primary_key) = explode('/', $name);
			$arguments['__primary_key'] = $primary_key;
		}
		$name = $name . '#' . $_SERVER['REQUEST_METHOD'];
		if ( !array_key_exists($name, $this->apiFunctions) ) {
			$this->createError("Function '" . $name . "' does not exist.", BAD_REQUEST);
		}
		$func = $this->apiFunctions[$name];
		// Arguments
		$args = array();
		foreach ( $arguments as $key => $value ) {
			if ( !array_key_exists($key, $func['optArgs']) && $key != '__primary_key' ) {
				$this->createError("Invalid argument '" . $key .
				                   "' for function '" . $name . "'.", BAD_REQUEST);
			}
			$args[$key] = $value;
		}
		$this->createResponse(call_user_func($func['callback'], $args));
	}

	/**
	 * Show documentation for the api and the registered functions
	 */
	public function showDocs()
	{
		ksort($this->apiFunctions);

		print "<!DOCTYPE html>\n";
		print "<html>\n";
		print "<head>\n";
		print "<meta charset=\"" . DJ_CHARACTER_SET . "\">";
		print "<title>DOMjudge version " . DOMJUDGE_VERSION . " REST API</title>\n";
		print "</head>\n";
		print "<body>\n";
		print "The supported functions are:\n";
		print "<dl>\n";
		foreach ( $this->apiFunctions as $key => $func ) {
			list($name, $method) = explode('#', $key);
			$url = $_SERVER['REQUEST_URI'] . $name;
			print '<dt><a href="' . $url . '">' . $url . "</a></dt>\n";
			print "<dd>";
			print "<p>" . $func['docs'] . "</p>\n";
			if ( count($func['optArgs']) > 0 ) {
				print "<p>Optional arguments:</p>\n<ul>\n";
				foreach($func['optArgs'] as $name => $desc) {
					print "<li><em>" . $name . "</em>: " . $desc . "</li>\n";
				}
				print "</ul>\n";
				if ( count($func['exArgs']) > 0 ) {
					print "<p>Example usage:<br />\n";
					foreach ( $func['exArgs'] as $exArg ) {
						$exUrl = $url . '?' . http_build_query($exArg);
						print '<a href="' . $exUrl . '">' . $exUrl . "</a><br />\n";
					}
					print "</p>\n";
				}
			}
			print "</dd>\n";
		}
		print "</dl>\n";
		print "</body>\n</html>\n";
	}

	private function createResponse($response)
	{
		header('Content-Type: application/json');
		print json_encode($response);
		exit;
	}

	public function createError($message, $code = BAD_REQUEST)
	{
		$protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
		header($protocol . " " . $code);
		$this->createResponse(array('error' => $message));
	}
}
