#!/usr/bin/env python3

# This tries to append an 'a' to each char, but this will
# break for input cases such as 'aa' as the team solution
# would contain the original string.

import sys

instruction, word = [x.strip() for x in sys.stdin.readlines()][0].split(' ')

final = ''
if instruction == 'jumble':
    for c in list(word):
        final += f"{c}a"
else:
    final = word[::2]
print(final)
