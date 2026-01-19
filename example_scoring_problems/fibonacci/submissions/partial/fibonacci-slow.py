#!/usr/bin/env python3
# Partial solution - O(n) iteration, times out on extreme cases
# @EXPECTED_RESULTS@: TIMELIMIT
# @EXPECTED_SCORE@: 75

def fibonacci(n):
    if n <= 1:
        return n
    a, b = 0, 1
    for _ in range(n - 1):
        a, b = b, a + b
    return b % (10**9 + 7)  # Modulo to avoid overflow issues

n = int(input())
print(fibonacci(n))
