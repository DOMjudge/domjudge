#!/usr/bin/env python3

# This translates the word to the index in the alphabet
# This is allowed but as this miscounts with off by one
# it fails.

import sys
import re


def translate(input_word: str, direction: bool) -> None:
    final = ''
    if direction:
        for c in list(input_word):
            final += f"{ord(c)-ord('a')+1:02}"
    else:
        for numb in re.findall(r"\d{2}", input_word):
            final += chr(ord('a') + int(numb))
    print(final)


instruction, word = [x.strip() for x in sys.stdin.readlines()][0].split(' ')

if instruction == 'jumble':
    translate(word, True)
else:
    translate(word, False)
