#!/usr/bin/env python3
# Optimal solution using dynamic programming
# Achieves maximum possible value
# @EXPECTED_RESULTS@: CORRECT
# @EXPECTED_SCORE@: 100

def solve():
    capacity = int(input())
    n = int(input())
    items = []
    for _ in range(n):
        w, v = map(int, input().split())
        items.append((w, v))

    # DP: dp[c] = max value achievable with capacity c
    dp = [0] * (capacity + 1)
    parent = [[] for _ in range(capacity + 1)]

    for i in range(n):
        w, v = items[i]
        # Iterate backwards to avoid using same item twice
        for c in range(capacity, w - 1, -1):
            if dp[c - w] + v > dp[c]:
                dp[c] = dp[c - w] + v
                parent[c] = parent[c - w] + [i]

    selected = parent[capacity]
    print(len(selected))
    if selected:
        print(' '.join(map(str, selected)))
    else:
        print()

solve()
