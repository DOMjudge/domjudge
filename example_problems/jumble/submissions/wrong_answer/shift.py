#!/usr/bin/env python3

# This moves one char to the end which fails for
# words consisting of the same letters.

import sys

instruction, word = [x.strip() for x in sys.stdin.readlines()][0].split(' ')

if instruction == 'jumble':
    print(word[1:]+word[:1])
else:
    print(word[-1]+word[:-1])
