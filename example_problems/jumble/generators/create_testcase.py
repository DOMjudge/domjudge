#!/usr/bin/env python3

import os


script_dir = os.path.dirname(os.path.realpath(__file__))
output_directory = os.path.join(script_dir, "..", "data/secret/same_string")

try:
    os.makedirs(output_directory)
except FileExistsError:
    pass

for i in set([1, 2, 13, 25]).union(set(range(0, 26, 4))):
    char = chr(i+ord('a'))
    with open(os.path.join(output_directory, f"{char}.in"), 'w') as f:
        f.write(f"jumble {char*(i+1)}\n")
    with open(os.path.join(output_directory, f"{char}.ans"), 'w') as f:
        f.write(f"{char*(i+1)}\n")
