#!/usr/bin/env python3
#
# Invoke as:
# <output_visualizer_program> input answer_file feedback_dir [additional_arguments] < team_output [ > team_input ]
import matplotlib.pyplot as plt
import sys

my_name  = sys.argv[0]
my_input = sys.argv[1]
#real_answer = sys.argv[2]
my_feedback = sys.argv[3]


plt.plot([map(int, x) for x in sys.stdin.readlines()])
plt.ylabel('Guesses')
plt.savefig(f"{feedback_dir}visual.png")
