'''
dj_utils -- Utility functions for other convenience scripts that come with DOMjudge.

Part of the DOMjudge Programming Contest Jury System and licensed
under the GNU GPL. See README and COPYING for details.
'''

import json
import os
import requests
import requests.utils
import sys

_myself = os.path.basename(sys.argv[0])
_default_user_agent = requests.utils.default_user_agent()
headers = {'user-agent': f'dj_utils/{_myself} ({_default_user_agent})'}
api_url = 'unset'


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

    # We got a successful HTTP response. It worked. Return the full response
    return json.loads(response.text)


def do_api_request(name: str):
    '''Perform an API call to the given endpoint and return its data.

    Parameters:
        name (str): the endpoint to call

    Returns:
        The endpoint contents.

    Raises:
        RuntimeError when the response is not JSON or the HTTP status code is non 2xx.
    '''

    url = f'{api_url}/{name}'

    try:
        response = requests.get(url, headers=headers)
    except requests.exceptions.RequestException as e:
        raise RuntimeError(e)

    return parse_api_response(name, response)


def upload_file(name: str, apifilename: str, file: str, data: dict = {}):
    '''Upload the given file to the API at the given path with the given name.

    Parameters:
        name (str): the endpoint to call
        apifilename (str): the argument name for the file to upload
        file (str): the file to upload

    Returns:
        The parsed endpoint contents.

    Raises:
        RuntimeError when the HTTP status code is non 2xx.
    '''

    files = [(apifilename, open(file, 'rb'))]

    url = f'{api_url}/{name}'

    response = requests.post(url, files=files, headers=headers, data=data)

    return parse_api_response(name, response)
