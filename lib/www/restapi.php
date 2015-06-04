<?php

/**
 * Functions for providing a REST API
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

define('DOMJUDGE_API_VERSION', 2);

define('BAD_REQUEST', '400 Bad Request');
define('FORBIDDEN', '403 Forbidden');
define('METHOD_NOT_ALLOWED', '405 Method Not Allowed');
define('INTERNAL_SERVER_ERROR', '500 Internal Server Error');

class RestApi {
	private $apiFunctions = array();

	/**
	 * Add a function to the list of functions that this API supports.
	 *
	 * Arguments:
	 * $httpMethod    Currently supported: GET, PUT, POST.
	 * $name          Name of the function.
	 * $docs          Documentation for this function.
	 * $optArgs       List of optional arguments.
	 * $exArgs        Example usage of arguments
	 * $roles         If a non-empty array, one of these roles is required
	 *                to use this action
	 * $allows_public If true, this function allows the 'public' optional
	 *                argument to only show public data, even for users with
	 *                more roles
	 */
	public function provideFunction($httpMethod, $name, $docs = '', $optArgs = array(),
	                                $exArgs = array(), $roles = null, $allows_public = false)
	{
		if ( !in_array($httpMethod, array('GET', 'POST', 'PUT')) ) {
			$this->createError("Only get/post/put methods supported.",
			                   INTERNAL_SERVER_ERROR);
		}
		if ( array_key_exists($name . '#' . $httpMethod, $this->apiFunctions) ) {
			$this->createError("Multiple definitions of " . $name .
			                   " for " . $httpMethod . ".", INTERNAL_SERVER_ERROR);
		}

		$callback = $name;
		if ( $httpMethod != 'GET' ) $callback .= '_' . $httpMethod;

		if ( $allows_public ) {
			$optArgs['public'] = 'only show public data, even for users with more roles';
		}

		$this->apiFunctions[$name . '#' . $httpMethod] =
			array("callback" => $callback,
			      "optArgs" => $optArgs,
			      "docs" => $docs,
			      "exArgs" => $exArgs,
			      "roles" => $roles);
	}

	/**
	 * Provide the actual API
	 */
	public function provideApi()
	{
		if ( !isset($_SERVER['PATH_INFO']) ) {
			$this->createError("PATH_INFO not set.", INTERNAL_SERVER_ERROR);
		}

		if ( !in_array($_SERVER['REQUEST_METHOD'],array('GET','POST','PUT')) ) {
			$this->createError("Only get/post/put methods supported.", METHOD_NOT_ALLOWED);
		}

		// trim off starting / of path_info
		$handler = preg_replace('#^/#', '', $_SERVER['PATH_INFO']);
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
		} else if ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {
			$postmax = phpini_to_bytes(trim(ini_get('post_max_size')));
			if ( $postmax != -1 && $postmax < $_SERVER['CONTENT_LENGTH'] ) {
				$this->createError("Size of post data too large (" . $_SERVER['CONTENT_LENGTH']
						. "), increase post_max_size (" . $postmax . ") in your PHP config.");
			}
		}
		$name = $name . '#' . $_SERVER['REQUEST_METHOD'];
		if ( !array_key_exists($name, $this->apiFunctions) ) {
			$this->createError("Function '" . $name . "' does not exist.", BAD_REQUEST);
		}
		$func = $this->apiFunctions[$name];
		// Permissions
		// no roles = anyone may access; admin may also access all
		if ( !empty($func['roles']) && !checkrole('admin') ) {
			$hasrole = false;
			foreach ($func['roles'] as $role) {
				if ( checkrole($role) ) {
					$hasrole = TRUE; break;
				}
			}
			if  ( ! $hasrole ) {
				$this->createError("Permission denied " .
				                   "' for function '" . $name . "'.", FORBIDDEN);
			}
		}

		// Arguments
		$args = array();
		foreach ( $arguments as $key => $value ) {
			if ( !array_key_exists($key, $func['optArgs']) && $key != '__primary_key' ) {
				$this->createError("Invalid argument '" . $key .
				                   "' for function '" . $name . "'.", BAD_REQUEST);
			}
			$args[$key] = $value;
		}

		// Special case for public:
		if ( array_key_exists('public', $func['optArgs']) )
		{
			if ( checkrole('jury') && !isset($args['public']) ) {
				// Default for jury is non-public
				$args['public'] = 0;
			} elseif ( !checkrole('jury') ) {
				// Only allowed for non-jury is public
				$args['public'] = 1;
			}
		}

		$this->createResponse(call_user_func($func['callback'], $args));
	}

	/**
	 * Show documentation for the api and the registered functions
	 */
	public function showDocs()
	{
		global $userdata;
		ksort($this->apiFunctions);

		print "<!DOCTYPE html>\n";
		print "<html>\n";
		print "<head>\n";
		print "<meta charset=\"" . DJ_CHARACTER_SET . "\">";
		print "<title>DOMjudge version " . DOMJUDGE_VERSION . " REST API</title>\n";
		print "</head>\n";
		print "<body>\n";
		print "<h1>DOMjudge REST API</h1>\n";
		print "<p>Welcome to the DOMjudge REST API.<br />";
		print "This is API version: " . DOMJUDGE_API_VERSION . "<br />\n";
		print "running on DOMjudge version: " . DOMJUDGE_VERSION . "</p>\n";
		print "<p>You are: ";
		if ( empty($userdata) ) {
			print "anonymous user";
		} else {
			print htmlspecialchars($userdata['username']) . " with roles ";
			$roles = $userdata['roles'];
			if ( !empty($userdata['teamid']) ) $roles[] = "team(".$userdata['teamid'].")";
			print implode(", ", $roles);
		}
		print "</p>\n";
		print "<p>The supported functions are:</p>\n";
		print "<dl>\n";
		foreach ( $this->apiFunctions as $key => $func ) {
			list($name, $method) = explode('#', $key);
			$url = $_SERVER['REQUEST_URI'] . $name;
			print '<dt><a href="' . $url . '">' . $url . "</a> ($method)</dt>\n";
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
						$exUrl = $url . '?' . http_build_query($exArg, null, '&amp;');
						print '<a href="' . $exUrl . '">' . $exUrl . "</a><br />\n";
					}
					print "</p>\n";
				}
			}
			print "<p>Required roles: ";
			print empty($func['roles']) ? "none" : implode (" or ", $func['roles']);
			print "</p>\n";
			print "</dd>\n";
		}
		print "</dl>\n";
		print "</body>\n</html>\n";
	}

	private function createResponse($response)
	{
		header('Content-Type: application/json');
		// TODO: use JSON_PRETTY_PRINT available in PHP >= 5.4.0?
		print json_encode($response);
		exit;
	}

	public function createError($message, $code = BAD_REQUEST)
	{
		$protocol = (isset($_SERVER['SERVER_PROTOCOL']) ?
		             $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
		header($protocol . " " . $code);
		$this->createResponse(array('error' => $message));
	}
}
