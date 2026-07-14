#!/usr/bin/env python3
#
# <output_visualizer_program> input_file answer_file feedback_dir [additional_arguments] < team_output

import sys
import os

inputfile = sys.argv[1]
answerfile = sys.argv[2]
feedbackdir = sys.argv[3]

team_output = [x.strip() for x in sys.stdin.readlines()]

provided_input = None
with open(inputfile, 'r') as inp:
    provided_input = [x.strip('\n') for x in inp.readlines()]

with open(answerfile, 'r') as out:
    expected_output = [x.strip('\n') for x in out.readlines()]

visualization = f"""
<svg xmlns='http://www.w3.org/2000/svg'>
    <text x='5' y='30' fill='gray' stroke='silver' font-size='35'>teamSubmission({provided_input})⇒</text>"""

for y in range(max(len(team_output), len(expected_output))):
    visualization += "\n"
    XOFFSET = 25
    maxx = 0
    try:
        print(team_output[y], file=sys.stderr)
        maxx = max(maxx, len(team_output[y]))
    except IndexError:
        pass
    try:
        print(expected_output[y], file=sys.stderr)
        maxx = max(maxx, len(expected_output[y]))
    except IndexError:
        pass
    line = ''
    if maxx > 0:
        visualization += "  "
    for x in range(maxx):
        try:
            a = team_output[y][x]
        except IndexError:
            a = '✗'
        try:
            b = expected_output[y][x]
        except IndexError:
            b = '✗'
        if a == b:
            visualization += f"<text x='{(x+1) * XOFFSET}' y='{(y+2) * 40}' font-size='35'>{a}</text>"
        else:
            print(ord(a), ord(b), file=sys.stderr)
            visualization += f"<text x='{(x+1) * XOFFSET}' y='{-20 + (y+2) * 40}' font-size='35'>{a}</text>"
            visualization += f"<text x='{(x+1) * XOFFSET}' y='{(y+2) * 40}' font-size='35'>-</text>"
            visualization += f"<text x='{(x+1) * XOFFSET}' y='{20 + (y+2) * 40}' font-size='35'>{b}</text>"
visualization += "\n  Sorry, your browser does not support inline SVG.\n</svg>"

with open(os.path.join(feedbackdir, 'judgeimage.svg'), 'w') as imagefile:
    print(visualization, file=imagefile)
