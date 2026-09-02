#!/usr/bin/env python3

import sys
import re


def translate(input_word: str, direction: bool) -> None:
    final = ''
    if direction:
        for c in list(input_word):
            final += f"{ord(c)-ord('a')+1:02}"
    else:
        for numb in re.findall(r"\d{2}", input_word):
            final += chr(ord('a') + int(numb)-1)
    print(final)


instruction, word = [x.strip() for x in sys.stdin.readlines()][0].split(' ')

if instruction == 'jumble':
    translate(word, True)
else:
    translate(word, False)
