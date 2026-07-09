#!/usr/bin/env python3

# <output_validator_program> input_file answer_file feedback_dir [additional_arguments] < team_output [ > team_input ]

import _io
import os
import random
import string
import sys
import tempfile


def strip_newline(raw: _io.TextIOWrapper) -> list[str]:
    return [x.strip() for x in raw.readlines()]


def check_readable(file: str, offset: int) -> list[str]:
    """offset -- Helper to make the exit code unique in the internal-error"""
    if os.path.isdir(file):
        print(f"Provided file: {file} is a directory.", file=sys.stderr)
        exit(DIRECTORY_ERROR+offset)
    try:
        with open(file, 'r') as f:
            return strip_newline(f)
    except PermissionError:
        exit(PERMISSION_ERROR+offset)
    except FileNotFoundError:
        exit(FILE_NOT_FOUND_ERROR+offset)


def check_writable(path: str) -> bool:
    try:
        if os.path.isdir(path):
            with tempfile.NamedTemporaryFile(dir=path, delete=True) as tmp:
                tmp.write(b"Access")
        else:
            with open(path, "a"):
                pass
        return True
    # Any error here indicates the judgehost is in the wrong state and should
    # be investigated
    except Exception:
        return False


input_file = sys.argv[1]
answer_file = sys.argv[2]
feedback_dir = sys.argv[3]

team_output = strip_newline(sys.stdin)

AC = 42
WA = 43

# All of those errors are unexpected and are errors with the judgehost
# They will be treated as internal-errors by the judgehost
PERMISSION_ERROR = 50
FILE_NOT_FOUND_ERROR = 51
ANSWER_FILE_FORMAT_ERROR = 52
SCRIPT_ERROR = 53
INPUT_FILE_ERROR = 54
DIRECTORY_ERROR = 55

if len(team_output) != 1:
    with open(os.path.join(feedback_dir, 'judgemessage.txt'), 'a') as judgemessage:
        print("Team output must only contain 1 line.", file=judgemessage)
    exit(WA)
else:
    team_output = team_output[0]

if len(team_output) != 1:
    with open(os.path.join(feedback_dir, 'judgemessage.txt'), 'a') as judgemessage:
        print("Team output must only contain 1 symbol.", file=judgemessage)
    exit(WA)
if team_output[0] not in string.ascii_lowercase:
    with open(os.path.join(feedback_dir, 'judgemessage.txt'), 'a') as judgemessage:
        print("Team output must only contain 1 lowercase letter.", file=judgemessage)
    exit(WA)

inp = check_readable(input_file, 0)
# Offset added to make the exit code unique.
out = check_readable(answer_file, 10)

assert check_writable(feedback_dir)

if len(inp) != 2:
    # There should be 2 lines,
    # - the guessed letters (initially empty)
    # - the open slots
    exit(INPUT_FILE_ERROR)
if len(inp[0]) >= 26:
    # There should never be more than 26 guessed letters,
    # as duplicate guesses are not allowed.
    exit(SCRIPT_ERROR)
if len(set(inp[1])-set(string.ascii_lowercase+"_ "))>0:
    # There should only be guessed letters,
    # unguessed indicated by '_'
    # or spaces for sentences.
    # Offset added to make the error unique.
    exit(SCRIPT_ERROR+10)
if len(out) != 1:
    exit(ANSWER_FILE_FORMAT_ERROR)

guessed_total = inp[0]
guessed_progress = inp[1]

if team_output in guessed_total:
    with open(os.path.join(feedback_dir, 'judgemessage.txt'), 'a') as judgemessage:
        print("Team guessed the same letter before.", file=judgemessage)
    exit(WA)

new = ''
for ci, c in enumerate(out[0]):
    if c == team_output:
        new += c
    else:
        new += inp[1][ci]

if new == out[0]:
    exit(AC)

guessed_total += team_output
guessed_total = list(guessed_total)
random.shuffle(guessed_total)
guessed_total = ''.join(guessed_total)

with open(os.path.join(feedback_dir, 'nextpass.in'), 'w') as nextpass:
    print(guessed_total, file=nextpass)
    print(new, file=nextpass)
exit(AC)
