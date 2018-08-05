<?php

/**
 * Functions for providing a REST API
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

define('BAD_REQUEST', '400 Bad Request');
define('FORBIDDEN', '403 Forbidden');
define('NOT_FOUND', '404 Not Found');
define('METHOD_NOT_ALLOWED', '405 Method Not Allowed');
define('INTERNAL_SERVER_ERROR', '500 Internal Server Error');

class RestApi
{
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
    public function provideFunction(
        $httpMethod,
        $name,
        $docs = '',
        $optArgs = array(),
                                    $exArgs = array(),
        $roles = null,
        $allows_public = false
    ) {
        if (!in_array($httpMethod, array('GET', 'POST', 'PUT'))) {
            $this->createError(
                "Only get/post/put methods supported.",
                               INTERNAL_SERVER_ERROR
            );
            return;
        }
        if (array_key_exists($name . '#' . $httpMethod, $this->apiFunctions)) {
            $this->createError("Multiple definitions of " . $name .
                               " for " . $httpMethod . ".", INTERNAL_SERVER_ERROR);
            return;
        }

        $callback = $name;
        if ($httpMethod != 'GET') {
            $callback .= '_' . $httpMethod;
        }

        if ($allows_public) {
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
    public function provideApi($multiContest = false)
    {
        if (!isset($_SERVER['PATH_INFO'])) {
            $this->createError("PATH_INFO not set.", INTERNAL_SERVER_ERROR);
            return;
        }

        if (!in_array($_SERVER['REQUEST_METHOD'], array('GET','POST','PUT'))) {
            $this->createError("Only get/post/put methods supported.", METHOD_NOT_ALLOWED);
            return;
        }

        // trim off starting / of path_info
        $handler = preg_replace('#^/#', '', $_SERVER['PATH_INFO']);
        if ($multiContest && mb_substr($handler, 0, mb_strlen("contests/")) === "contests/") {
            global $requestedCid, $DB;
            $handler = preg_replace('#contests/#', '', $handler);
            $cid = preg_replace('#/.*#', '', $handler);
            $requestedCid = $DB->q('MAYBEVALUE SELECT cid FROM contest WHERE cid=%s', $cid);
            if (!isset($requestedCid)) {
                $this->createError("Contest not found.", NOT_FOUND);
                return;
            }
            $handler = preg_replace('#[^/]*/#', '', $handler, 1);
        }
        if (empty($handler)) {
            $this->showDocs();
        } else {
            if ($_SERVER['REQUEST_METHOD'] == 'GET') {
                $this->callFunction($handler, $_GET);
            } elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $this->callFunction($handler, $_POST);
            } elseif ($_SERVER['REQUEST_METHOD'] == 'PUT') {
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
        global $userdata;
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $postmax = phpini_to_bytes(trim(ini_get('post_max_size')));
            if ($postmax > 0 && $postmax < $_SERVER['CONTENT_LENGTH']) {
                $this->createError("Size of post data too large (" . $_SERVER['CONTENT_LENGTH']
                        . "), increase post_max_size (" . $postmax . ") in your PHP config.");
                return;
            }
        }
        if (strpos($name, "/") !== false) {
            list($name, $primary_key) = preg_split('/\/+/', $name, 2);
            if (isset($primary_key) && $primary_key!=='') {
                // Primary key can either be one or multiple ID's joined by ",", so split them
                // TODO: if some ID's contain a comma, this breaks
                $arguments['__primary_key'] = explode(',', $primary_key);
            }
        }
        $name = $name . '#' . $_SERVER['REQUEST_METHOD'];
        if (!array_key_exists($name, $this->apiFunctions)) {
            $name_without_dashes = str_replace("-", "_", $name);
            if (array_key_exists($name_without_dashes, $this->apiFunctions)) {
                $name = $name_without_dashes;
            } else {
                $this->createError("Function '" . $name . "' does not exist.", BAD_REQUEST);
                return;
            }
        }
        $func = $this->apiFunctions[$name];
        // Permissions
        // no roles = anyone may access; admin may also access all
        if (!empty($func['roles']) && !checkrole('admin')) {
            $hasrole = false;
            foreach ($func['roles'] as $role) {
                if (checkrole($role)) {
                    $hasrole = true;
                    break;
                }
            }
            if (! $hasrole) {
                $roles = array();
                if (is_array($userdata['roles'])) {
                    $roles = $userdata['roles'];
                }
                $this->createError("Permission denied for function '$name'" .
                                   " to user '$userdata[name]' with roles " .
                                   implode(',', $roles) . '.', FORBIDDEN);
                return;
            }
        }

        // Arguments
        $args = array();
        $valid_args = array_merge($func['optArgs'], array(
            '__primary_key' => 'ID of single element endpoint or multiple ID\'s separated by a comma',
            'strict'        => 'Strictly follow Contest API specification',
        ));
        foreach ($arguments as $key => $value) {
            if (!array_key_exists($key, $valid_args)) {
                $this->createError("Invalid argument '" . $key .
                                   "' for function '" . $name . "'.", BAD_REQUEST);
                return;
            }
            $args[$key] = $value;
        }

        // Special case for public:
        if (array_key_exists('public', $func['optArgs'])) {
            if (checkrole('jury') && !isset($args['public'])) {
                // Default for jury is non-public
                $args['public'] = 0;
            } elseif (!checkrole('jury')) {
                // Only allowed for non-jury is public
                $args['public'] = 1;
            }
        }

        try {
            $response = call_user_func($func['callback'], $args);
        } catch (RuntimeException $e) {
            $this->createError("Server-side runtime exception: " . $e->getMessage(), INTERNAL_SERVER_ERROR);
        }
        if ($response === '') {
            // We receive an empty response of a createError or checkargs produces an error
            // In that case, just return
            $this->createResponse($response);
            return;
        }
        // If a single element was requested, return an object, but only for non-multiple requests:
        if (isset($arguments['__primary_key']) &&
            count($arguments['__primary_key']) === 1 &&
            $_SERVER['REQUEST_METHOD']==='GET') {
            if (count($response)!=1) {
                $this->createError("Found " . count($response) .
                                   " elements with ID '" . implode(',', $arguments['__primary_key']) .
                                   "' for function '" . $name . "'.", NOT_FOUND);
                return;
            }
            $response = reset($response);
        }

        $this->createResponse($response);
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
        if (empty($userdata)) {
            print "anonymous user";
        } else {
            print specialchars($userdata['username']) . " with roles ";
            $roles = $userdata['roles'];
            if (!empty($userdata['teamid'])) {
                $roles[] = "team(".$userdata['teamid'].")";
            }
            print implode(", ", $roles);
        }
        print "</p>\n";
        print "<p>The supported functions are:</p>\n";
        print "<dl>\n";
        foreach ($this->apiFunctions as $key => $func) {
            list($name, $method) = explode('#', $key);
            $url = $_SERVER['REQUEST_URI'] . $name;
            print '<dt><a href="' . $url . '">' . $url . "</a> ($method)</dt>\n";
            print "<dd>";
            print "<p>" . $func['docs'] . "</p>\n";
            if (count($func['optArgs']) > 0) {
                print "<p>Optional arguments:</p>\n<ul>\n";
                foreach ($func['optArgs'] as $name => $desc) {
                    print "<li><em>" . $name . "</em>: " . $desc . "</li>\n";
                }
                print "</ul>\n";
                if (count($func['exArgs']) > 0) {
                    print "<p>Example usage:<br />\n";
                    foreach ($func['exArgs'] as $exArg) {
                        $exUrl = $url . '?' . http_build_query($exArg, null, '&amp;');
                        print '<a href="' . $exUrl . '">' . $exUrl . "</a><br />\n";
                    }
                    print "</p>\n";
                }
            }
            print "<p>Required roles: ";
            print empty($func['roles']) ? "none" : implode(" or ", $func['roles']);
            print "</p>\n";
            print "</dd>\n";
        }
        print "</dl>\n";
        print "</body>\n</html>\n";
    }

    private function createResponse($response)
    {
        // Only send headers if not done already. Headers might be sent if calling the API internally
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        print dj_json_encode($response) . "\n";
    }

    public function createError($message, $code = BAD_REQUEST)
    {
        // Only send headers if not done already. Headers might be sent if calling the API internally
        if (!headers_sent()) {
            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ?
                         $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
            header($protocol . " " . $code);
        }
        $this->createResponse(array('error' => $message));
    }
}
