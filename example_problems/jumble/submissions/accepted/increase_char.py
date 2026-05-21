#!/usr/bin/env python3

import sys


def translate(input_word: str, direction: int) -> None:
    final = ''
    for c in list(input_word):
        cid = chr(ord('a') + (ord(c)-ord('a')+direction) % 26)
        final += cid
    print(final)


instruction, word = [x.strip() for x in sys.stdin.readlines()][0].split(' ')

if instruction == 'jumble':
    translate(word, 1)
else:
    translate(word, -1)
