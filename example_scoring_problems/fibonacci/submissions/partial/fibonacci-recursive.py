#!/usr/bin/env python3
# Partial solution - naive recursion, only works for very small n
# @EXPECTED_RESULTS@: TIMELIMIT
# @EXPECTED_SCORE@: 25
import sys
sys.setrecursionlimit(1000000)

def fibonacci(n):
    if n <= 1:
        return n
    return fibonacci(n - 1) + fibonacci(n - 2)

n = int(input())
print(fibonacci(n))
