#!/usr/bin/env python3
# Efficient Kadane's but fails on edge cases:
# - All negative numbers: incorrectly returns 0
# - Single element: works
# - Overflow: works (Python handles big ints)
# Since robustness uses MIN aggregation and we fail negative, we get 0 for robustness
# @EXPECTED_RESULTS@: WRONG-ANSWER
# @EXPECTED_SCORE@: 50

def kadane_buggy(arr):
    """Buggy Kadane's that assumes at least one positive number"""
    max_sum = 0  # Bug: should start with arr[0]
    curr_sum = 0
    for num in arr:
        curr_sum = max(0, curr_sum + num)  # Bug: should be max(num, curr_sum + num)
        max_sum = max(max_sum, curr_sum)
    return max_sum

n = int(input())
arr = list(map(int, input().split()))
print(kadane_buggy(arr))
