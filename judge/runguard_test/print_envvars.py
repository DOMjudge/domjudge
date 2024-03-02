#!/usr/bin/python3

import os

envvars = os.environ.items()
print(f'COUNT: {len(envvars)}.')
for k, v in envvars:
    print(f'{k}={v}')

