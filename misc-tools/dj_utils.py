'''
dj_utils -- Utility functions for other convenience scripts that come with DOMjudge.

Part of the DOMjudge Programming Contest Jury System and licensed
under the GNU GPL. See README and COPYING for details.
'''

import json
import os
import requests
import requests.utils
import subprocess
import sys
from urllib.parse import urlparse

_myself = os.path.basename(sys.argv[0])
_default_user_agent = requests.utils.default_user_agent()
headers = {'user-agent': f'dj_utils/{_myself} ({_default_user_agent})'}
domjudge_webapp_folder_or_api_url = 'unset'
ca_check = True


def confirm(message: str, default: bool) -> bool:
    answer = 'x'
    while answer not in ['y', 'n']:
        yn = 'Y/n' if default else 'y/N'
        answer = input(f'{message} ({yn}) ').lower()
        if answer == '':
            answer = 'y' if default else 'n'
    return answer == 'y'


def parse_api_response(name: str, response: requests.Response) -> bytes:
    # The connection worked, but we may have received an HTTP error
    if response.status_code >= 300:
        print(response.text)
        if response.status_code == 401:
            raise RuntimeError(
                'Authentication failed, please check your DOMjudge credentials in ~/.netrc.')
        else:
            raise RuntimeError(
                f'API request {name} failed (code {response.status_code}).')

    if response.status_code == 204:
        return None

    return response.content


def do_api_request(name: str, method: str = 'GET', jsonData: dict = {}, decode: bool = True):
    '''Perform an API call to the given endpoint and return its data.

    Based on whether `domjudge_webapp_folder_or_api_url` is a folder or URL this
    will use the DOMjudge CLI or HTTP API.

    Parameters:
        name (str): the endpoint to call
        method (str): the method to use, GET or PUT are supported
        jsonData (dict): the JSON data to PUT. Only used when method is PUT
        decode (bool): whether to decode the returned JSON data, default true

    Returns:
        The endpoint contents, either as raw bytes or JSON decoded.

    Raises:
        RuntimeError when the HTTP status code is non-2xx or the response
        cannot be JSON decoded.
    '''

    if os.path.isdir(domjudge_webapp_folder_or_api_url):
        result = api_via_cli(name, method, {}, {}, jsonData)
    else:
        global ca_check
        url = f'{domjudge_webapp_folder_or_api_url}/{name}'
        parsed = urlparse(domjudge_webapp_folder_or_api_url)
        auth = None
        if parsed.username and parsed.password:
            auth = (parsed.username, parsed.password)

        try:
            if method == 'GET':
                response = requests.get(url, headers=headers, verify=ca_check, auth=auth)
            elif method == 'PUT':
                response = requests.put(url, headers=headers, verify=ca_check, auth=auth, json=jsonData)
            else:
                raise RuntimeError("Method not supported")
        except requests.exceptions.SSLError:
            ca_check = not confirm(
                "Can not verify certificate, ignore certificate check?", False)
            if ca_check:
                print('Can not verify certificate chain for DOMserver.')
                exit(1)
            else:
                return do_api_request(name)
        except requests.exceptions.RequestException as e:
            raise RuntimeError(e)
        result = parse_api_response(name, response)

    if decode:
        try:
            result = json.loads(result)
        except json.decoder.JSONDecodeError as e:
            print(result)
            raise RuntimeError(f'Failed to JSON decode the response for API request {name}')

    return result


def upload_file(name: str, apifilename: str, file: str, data: dict = {}):
    '''Upload the given file to the API at the given path with the given name.

    Based on whether `domjudge_webapp_folder_or_api_url` is a folder or URL this
    will use the DOMjudge CLI or HTTP API.

    Parameters:
        name (str): the endpoint to call
        apifilename (str): the argument name for the file to upload
        file (str): the file to upload

    Returns:
        The parsed endpoint contents.

    Raises:
        RuntimeError when the HTTP status code is non 2xx.
    '''

    if os.path.isdir(domjudge_webapp_folder_or_api_url):
        result = api_via_cli(name, 'POST', data, {apifilename: file})
    else:
        global ca_check
        files = [(apifilename, open(file, 'rb'))]

        url = f'{domjudge_webapp_folder_or_api_url}/{name}'

        try:
            response = requests.post(
                url, files=files, headers=headers, data=data, verify=ca_check)
        except requests.exceptions.SSLError:
            ca_check = not confirm(
                "Can not verify certificate, ignore certificate check?", False)
            if ca_check:
                print('Can not verify certificate chain for DOMserver.')
                exit(1)
            else:
                response = requests.post(
                    url, files=files, headers=headers, data=data, verify=ca_check)

        result = parse_api_response(name, response)

    if result is not None:
        try:
            result = json.loads(result)
        except json.decoder.JSONDecodeError as e:
            print(result)
            raise RuntimeError(f'Failed to JSON decode the response for API file upload request {name}')

    return result


def api_via_cli(name: str, method: str = 'GET', data: dict = {}, files: dict = {}, jsonData: dict = {}):
    '''Perform the given API request using the CLI

    Parameters:
        name (str): the endpoint to call
        method (str): the method to use. Either GET, POST or PUT
        data (dict): the POST data to use. Only used when method is POST or PUT
        files (dict): the files to use. Only used when method is POST or PUT
        jsonData (dict): the JSON data to use. Only used when method is POST or PUT

    Returns:
        The raw endpoint contents.

    Raises:
        RuntimeError when the command exit code is not 0.
    '''

    command = [
        f'{domjudge_webapp_folder_or_api_url}/bin/console',
        'api:call',
        '-m',
        method
    ]

    for item in data:
        command.extend(['-d', f'{item}={data[item]}'])

    for item in files:
        command.extend(['-f', f'{item}={files[item]}'])

    if jsonData:
        command.extend(['-j', json.dumps(jsonData)])

    command.append(name)

    result = subprocess.run(command, capture_output=True)

    if result.returncode != 0:
        print(
            f"Command: {command}\nOutput:\n" +
            result.stdout.decode('utf-8') +
            result.stderr.decode('utf-8'),
            file=sys.stderr
        )
        raise RuntimeError(f'API request {name} failed')

    return result.stdout
