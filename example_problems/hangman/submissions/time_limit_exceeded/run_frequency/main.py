#!/usr/bin/env python3

# This should be too slow as it need to parse all known words and create
# a frequency ordering during runtime for each new pass.

import words
import re
words = words.words

guessed = input()
todo = input()

new_guessed = todo.replace("_", ".")

matches = []
while matches == []:
    # Try to find the word first, otherwise a partial fitting word
    for regex in [f"^{new_guessed}$", f"^.*{new_guessed}.*$"]:
        matches = [w for w in words if re.match(regex, w)]
        if matches != []:
            break
    if matches == []:
        possible = list(sorted(set(guessed)))
        new_guessed = guessed.replace("_", ".").replace(possible[0], ".")
    else:
        break

cntr = {}
for m in matches:
    for c in set(m)-set(guessed):
        if c not in cntr:
            cntr[c] = 0
        cntr[c] += 1

cntr = dict(sorted(cntr.items(), key=lambda item: item[1]))
pick = len(cntr.keys()) % max(len(guessed), 1)
if pick >= len(cntr.keys()):
    pick = len(guessed) % len(cntr.keys())
print(list(cntr.keys())[pick])
