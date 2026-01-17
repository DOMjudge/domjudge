#!/usr/bin/env python3
# Greedy solution: pick items by value only (ignoring weight efficiency)
# Achieves suboptimal results
# @EXPECTED_SCORE@: 88.37

def solve():
    capacity = int(input())
    n = int(input())
    items = []
    for i in range(n):
        w, v = map(int, input().split())
        items.append((w, v, i))

    # Sort by value (descending) - ignores weight efficiency
    items.sort(key=lambda x: -x[1])

    selected = []
    remaining = capacity

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
