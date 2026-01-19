#!/usr/bin/env python3
# Handles small and medium numbers correctly, fails on large
# Uses 32-bit integer simulation
# @EXPECTED_RESULTS@: WRONG-ANSWER
# @EXPECTED_SCORE@: 30

import sys

n = int(input())

# Simulate 32-bit signed integer overflow
INT_MAX = 2147483647

if n <= INT_MAX:
    print(n + 1)
else:
    # Overflow behavior - outputs wrong answer
    print((n + 1) % (INT_MAX + 1))
