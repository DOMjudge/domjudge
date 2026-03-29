#!/usr/bin/env python3

import os
import random
import sys
import re


def write_input_and_answer(word: str, testcase_number: int, output_dir: str) -> None:
    hangman_string = re.sub("[a-z]", "_", word)
    testcase_number += 1
    with open(f"{output_dir}/{testcase_number}.in", 'w') as f:
        f.write("\n")
        f.write(f"{hangman_string}\n")
    with open(f"{output_dir}/{testcase_number}.ans", 'w') as f:
        f.write(f"{word}\n")


if (len(sys.argv) not in [2, 3]):
    print(f"Usage: {__file__} <number_of_testcases> [example_word_file]")
    exit(1)

script_dir = os.path.dirname(os.path.realpath(__file__))
output_directory = os.path.join(script_dir, "..", "data/secret")
requested_number_testcases = int(sys.argv[1])
wordfile = "/usr/share/dict/words"

try:
    wordfile = sys.argv[2]
except IndexError:
    pass

try:
    os.makedirs(output_directory)
except FileExistsError:
    pass

acceptable_words = []
pattern = re.compile("[a-z]+")

try:
    with open(wordfile, 'r') as wordfile:
        for line in wordfile.readlines():
            line = line.strip()
            # If we want the time_limit_exceeded solution to know all the words
            # we need words of max length 7 otherwise the sourcecode gets to large
            # if len(l) > 7:
            #  continue
            if pattern.fullmatch(line):
                acceptable_words.append(line)
except FileNotFoundError:
    print(f"Wordfile not found: {wordfile}")
    exit(2)

random.shuffle(acceptable_words)

try:
    for i in range(requested_number_testcases):
        write_input_and_answer(acceptable_words[i], i, output_directory)
except IndexError:
    print(f"Requested {requested_number_testcases} but only found {len(acceptable_words)}.")
    exit(3)

output_directory = os.path.join(output_directory, "with_spaces")
try:
    os.makedirs(output_directory)
except FileExistsError:
    pass

space_sentences = ["the quick brown fox jumps over the lazy dog", " starts with space"]
for index, sentence in enumerate(space_sentences):
    write_input_and_answer(sentence, index, output_directory)
