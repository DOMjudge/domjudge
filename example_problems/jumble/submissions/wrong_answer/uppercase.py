#!/usr/bin/env python3

# Uppercase letters are not allowed for the answer & intermediate passes.

import sys

instruction, word = [x.strip() for x in sys.stdin.readlines()][0].split(' ')

if instruction == 'jumble':
    print(word.upper())
else:
    print(word.lower())
