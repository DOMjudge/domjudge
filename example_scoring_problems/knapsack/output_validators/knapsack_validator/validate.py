#!/usr/bin/env python3
"""
Knapsack output validator for DOMjudge scoring problems.

Reads the team's selection, validates it doesn't exceed capacity,
calculates the achieved value, and outputs a score based on
how close it is to the optimal solution.

Exit codes:
  42 = Accepted (valid solution)
  43 = Wrong Answer (invalid solution - over capacity or invalid indices)
"""

import sys
import os

def main():
    if len(sys.argv) < 4:
        print("Usage: validate.py <input> <answer> <feedback_dir>", file=sys.stderr)
        sys.exit(1)

    input_file = sys.argv[1]
    answer_file = sys.argv[2]
    feedback_dir = sys.argv[3]

    # Read input
    with open(input_file, 'r') as f:
        capacity = int(f.readline().strip())
        n = int(f.readline().strip())
        items = []
        for _ in range(n):
            w, v = map(int, f.readline().split())
            items.append((w, v))

    # Read judge's answer to get optimal value
    with open(answer_file, 'r') as f:
        k_opt = int(f.readline().strip())
        if k_opt > 0:
            opt_indices = list(map(int, f.readline().split()))
        else:
            opt_indices = []

    optimal_value = sum(items[i][1] for i in opt_indices)

    # Read team output from stdin
    try:
        team_output = sys.stdin.read().strip()
        if not team_output:
            write_feedback(feedback_dir, 0, "Empty output")
            sys.exit(43)

        lines = team_output.split('\n')
        k = int(lines[0].strip())
        if k > 0:
            if len(lines) < 2:
                write_feedback(feedback_dir, 0, "Missing item indices")
                sys.exit(43)
            selected = list(map(int, lines[1].split()))
        else:
            selected = []

        if len(selected) != k:
            write_feedback(feedback_dir, 0, f"Expected {k} items but got {len(selected)}")
            sys.exit(43)

    except (ValueError, IndexError) as e:
        write_feedback(feedback_dir, 0, f"Parse error: {e}")
        sys.exit(43)

    # Validate selection
    # Check for valid indices (0-indexed)
    for idx in selected:
        if idx < 0 or idx >= n:
            write_feedback(feedback_dir, 0, f"Invalid item index: {idx}")
            sys.exit(43)

    # Check for duplicates
    if len(selected) != len(set(selected)):
        write_feedback(feedback_dir, 0, "Duplicate items in selection")
        sys.exit(43)

    # Calculate total weight and value
    total_weight = sum(items[i][0] for i in selected)
    total_value = sum(items[i][1] for i in selected)

    # Check capacity constraint
    if total_weight > capacity:
        write_feedback(feedback_dir, 0, f"Weight {total_weight} exceeds capacity {capacity}")
        sys.exit(43)

    # Calculate score (0-100 based on achieved value vs optimal)
    if optimal_value == 0:
        score = 100.0 if total_value == 0 else 0.0
    else:
        score = (total_value / optimal_value) * 100.0

    # Cap at 100 in case team found better solution than judge
    score = min(100.0, score)

    write_feedback(feedback_dir, score,
                   f"Value: {total_value}/{optimal_value}, Weight: {total_weight}/{capacity}")
    sys.exit(42)


def write_feedback(feedback_dir, score, message):
    """Write score and message to feedback files."""
    os.makedirs(feedback_dir, exist_ok=True)

    with open(os.path.join(feedback_dir, 'score.txt'), 'w') as f:
        f.write(f"{score:.2f}\n")

    with open(os.path.join(feedback_dir, 'judgemessage.txt'), 'w') as f:
        f.write(message + "\n")


if __name__ == '__main__':
    main()
