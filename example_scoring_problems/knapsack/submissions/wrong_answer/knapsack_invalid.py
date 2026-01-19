#!/usr/bin/env python3
# Invalid solution: tries to take everything (exceeds capacity)
# @EXPECTED_RESULTS@: WRONG-ANSWER
# @EXPECTED_SCORE@: 0

def solve():
    capacity = int(input())
    n = int(input())
    items = []
    for i in range(n):
        w, v = map(int, input().split())
        items.append((w, v))

    # Take all items (will exceed capacity)
    print(n)
    print(' '.join(map(str, range(n))))

solve()
