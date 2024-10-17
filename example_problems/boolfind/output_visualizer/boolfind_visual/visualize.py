#!/usr/bin/env python3
#
# Invoke as:
# <output_visualizer_program> input answer_file feedback_dir [additional_arguments] < team_output [ > team_input ]
import matplotlib.pyplot as plt
import sys

my_name  = sys.argv[0]
my_input = sys.argv[1]
#real_answer = sys.argv[2]
my_feedback = sys.argv[2]

with open(my_input, 'r') as f:
  lines = f.readlines()
  vals = []
  for line in lines:
    if 'READ' in line:
      vals.append(int(line.split(' ')[-1]))
      print(line)
  plt.plot([0,1])
  plt.ylabel('Guesses')
  plt.savefig(f"{my_feedback}")
