#!/usr/bin/env python3
# Handles medium numbers correctly, fails on small and large
# Uses 32-bit integer simulation
# @EXPECTED_RESULTS@: WRONG-ANSWER
# @EXPECTED_SCORE@: 20

import sys

n = int(input())

# Simulate 32-bit signed integer overflow
INT_MAX = 2147483647

if n > 100 and n <= INT_MAX:
    print(n + 1)
else:
    print(n - 1)
