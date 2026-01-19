#!/usr/bin/env python3
# Greedy solution: pick items by value/weight ratio
# Usually achieves good but not optimal results
# @EXPECTED_RESULTS@: CORRECT
# @EXPECTED_SCORE@: 95.35

def solve():
    capacity = int(input())
    n = int(input())
    items = []
    for i in range(n):
        w, v = map(int, input().split())
        items.append((w, v, i, v / w))  # weight, value, index, ratio

    # Sort by value/weight ratio (descending)
    items.sort(key=lambda x: -x[3])

    selected = []
    remaining = capacity

    for w, v, idx, ratio in items:
        if w <= remaining:
            selected.append(idx)
            remaining -= w

    print(len(selected))
    if selected:
        print(' '.join(map(str, selected)))
    else:
        print()

solve()
