#!/usr/bin/env python3

import sys
import re


def translate(word: str, direction: bool) -> None:
    final = ''
    if direction:
        for c in list(word):
            final += f"{ord(c)-ord('a')+1:02}"
    else:
        for numb in re.findall(r"\d{2}", word):
            final += chr(ord('a') + int(numb)-1)
    print(final)


instruction, word = [x.strip() for x in sys.stdin.readlines()][0].split(' ')

if instruction == 'jumble':
    translate(word, True)
else:
    translate(word, False)
