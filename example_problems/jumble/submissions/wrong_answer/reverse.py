#!/usr/bin/env python3

# Reversing the string fails for palindromes

import sys

_, word = [x.strip() for x in sys.stdin.readlines()][0].split(' ')

print(word[::-1])
