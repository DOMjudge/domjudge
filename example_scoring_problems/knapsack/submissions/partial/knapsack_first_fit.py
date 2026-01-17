#!/usr/bin/env python3
# Simple first-fit: pick items in order until full
# Achieves low partial score
# @EXPECTED_SCORE@: 74.42

def solve():
    capacity = int(input())
    n = int(input())
    items = []
    for i in range(n):
        w, v = map(int, input().split())
        items.append((w, v, i))

    selected = []
    remaining = capacity

    # Just pick items in input order
    for w, v, idx in items:
        if w <= remaining:
            selected.append(idx)
            remaining -= w

    print(len(selected))
    if selected:
        print(' '.join(map(str, selected)))
    else:
        print()

solve()
