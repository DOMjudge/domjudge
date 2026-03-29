#!/usr/bin/env python3

# <output_validator_program> input_file answer_file feedback_dir [additional_arguments] < team_output [ > team_input ]

import sys
import re
import os
import _io
import tempfile


def strip_newline(raw: _io.TextIOWrapper) -> list[str]:
    return [x.strip() for x in raw.readlines()]


def check_readable(file: str, offset: int) -> list[str]:
    """offset -- Helper to make the exit code unique in the internal-error"""
    if os.path.isdir(file):
        return None
    try:
        with open(file, 'r') as f:
            return strip_newline(f)
    except PermissionError:
        exit(PERMISSION_ERROR+offset)
    except FileNotFoundError:
        exit(FILE_ERROR+offset)


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
PERMISSION_ERROR = 51
FILE_ERROR = 52
INPUT_FORMAT_ERROR = 53
ANSWER_FORMAT_ERROR = 54
REGEX_ERROR = 55

if len(team_output) != 1:
    with open(os.path.join(feedback_dir, 'judgemessage.txt'), 'a') as judgemessage:
        print("Team output must only contain 1 line.", file=judgemessage)
    exit(WA)
else:
    team_output = team_output[0]

inp = check_readable(input_file, 0)
# Offset added to make the exit code unique.
out = check_readable(answer_file, 10)

# The feedback dir should be writable and normally this doesn´t need to
# be checked. This is checked to make sure DOMjudge works as expected.
assert check_writable(feedback_dir)

if len(inp) != 1:
    # The judge testcase input (initial or nextpass) is in the wrong format,
    # it has more than 1 line. This should have been detected by the
    # input_validator and is normally not checked by the output_validator.
    exit(INPUT_FORMAT_ERROR)
inp = inp[0].split(' ')
if len(inp) != 2:
    # The judge testcase input (initial or nextpass) is in the wrong format,
    # it has more/less than 2 words on the line. This should have been detected
    # by the input_validator and is normally not checked by the output_validator.
    # Offset of 10 to make the exit code unique.
    exit(INPUT_FORMAT_ERROR+10)

if len(out) != 1:
    # The judge provided answer is in the wrong format, it has more than 1 word
    # This should have been detected by the answer_validator and is normally
    # not checked by the output_validator
    exit(ANSWER_FORMAT_ERROR)

if inp[0] == 'unjumble':
    if out[0] == team_output:
        exit(AC)
    with open(os.path.join(feedback_dir, 'judgemessage.txt'), 'a') as judgemessage:
        print("Team output after jumbling & unjumbling is not original input", file=judgemessage)
    exit(WA)

if inp[0] == 'jumble':
    pattern = re.compile("[a-z]+")
    if not pattern.fullmatch(inp[1]):
        # The judge provided input case contains illegal characters
        # This should have been detected by the input_validator and
        # is normally not checked by the output_validator
        exit(REGEX_ERROR)
    pattern = re.compile("[a-z0-9]+")
    if pattern.fullmatch(team_output):
        if inp[1] in team_output:
            with open(os.path.join(feedback_dir, 'judgemessage.txt'), 'a') as judgemessage:
                print("The original input was found in the team output", file=judgemessage)
            exit(WA)
        with open(os.path.join(feedback_dir, 'nextpass.in'), 'w') as nextpass:
            print(f"unjumble {team_output}", file=nextpass)
        exit(AC)
    with open(os.path.join(feedback_dir, 'judgemessage.txt'), 'a') as judgemessage:
        print("Team output doesn´t match regex [a-z0-9]", file=judgemessage)
    exit(WA)

# The presented input file (initial or generated nextpass)
# lacks the required format. Offset of 20 added to make the error code unique.
exit(INPUT_FORMAT_ERROR+20)
