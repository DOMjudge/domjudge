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


def parse_api_response(name: str, response: requests.Response):
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

    # We got a successful HTTP response. It worked. Return the full response
    return json.loads(response.text)


def do_api_request(name: str):
    '''Perform an API call to the given endpoint and return its data.

    Based on whether `domjudge_webapp_folder_or_api_url` is a folder or URL this
    will use the DOMjudge CLI or HTTP API.

    Parameters:
        name (str): the endpoint to call

    Returns:
        The endpoint contents.

    Raises:
        RuntimeError when the response is not JSON or the HTTP status code is non 2xx.
    '''

    if os.path.isdir(domjudge_webapp_folder_or_api_url):
        return api_via_cli(name)
    else:
        global ca_check
        url = f'{domjudge_webapp_folder_or_api_url}/{name}'

        try:
            response = requests.get(url, headers=headers, verify=ca_check)
        except requests.exceptions.SSLError as e:
            ca_check = not confirm(
                "Can not verify certificate, ignore certificate check?", False)
            if ca_check:
                print('Can not verify certificate chain for DOMserver.')
                exit(1)
            else:
                return do_api_request(name)
        except requests.exceptions.RequestException as e:
            raise RuntimeError(e)
    return parse_api_response(name, response)


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
        return api_via_cli(name, 'POST', data, {apifilename: file})
    else:
        global ca_check
        files = [(apifilename, open(file, 'rb'))]

        url = f'{domjudge_webapp_folder_or_api_url}/{name}'

        try:
            response = requests.post(
                url, files=files, headers=headers, data=data, verify=ca_check)
        except requests.exceptions.SSLError as e:
            ca_check = not confirm(
                "Can not verify certificate, ignore certificate check?", False)
            if ca_check:
                print('Can not verify certificate chain for DOMserver.')
                exit(1)
            else:
                response = requests.post(
                    url, files=files, headers=headers, data=data, verify=ca_check)

    return parse_api_response(name, response)


def api_via_cli(name: str, method: str = 'GET', data: dict = {}, files: dict = {}):
    '''Perform the given API request using the CLI

    Parameters:
        name (str): the endpoint to call
        method (str): the method to use. Either GET or POST
        data (dict): the POST data to use. Only used when method is POST
        files (dict): the files to use. Only used when method is POST

    Returns:
        The parsed endpoint contents.

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

    command.append(name)

    result = subprocess.run(command, capture_output=True)
    response = result.stdout.decode('ascii')

    if result.returncode != 0:
        print(response)
        raise RuntimeError(f'API request {name} failed')

    return json.loads(response)
