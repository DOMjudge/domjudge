#!/usr/bin/env python3
# Efficient Kadane's but fails on all robustness edge cases
# Since robustness uses MIN and we fail all, we get 0 for robustness
# basic (20) + efficiency (30) + robustness (0) = 50
# @EXPECTED_RESULTS@: WRONG-ANSWER
# @EXPECTED_SCORE@: 50

def kadane_efficiency(arr):
    """Efficient but breaks on edge cases"""
    # Fail on single element
    if len(arr) == 1:
        return -999999

    # Fail on all negative
    if all(x < 0 for x in arr):
        return 0

    # Fail on overflow (use int32 simulation)
    INT_MAX = 2147483647
    max_sum = 0
    curr_sum = 0
    for num in arr:
        curr_sum = max(0, curr_sum + num)
        max_sum = max(max_sum, curr_sum)
        if max_sum > INT_MAX:
            max_sum = max_sum % INT_MAX  # Wrong for overflow
    return max_sum

n = int(input())
arr = list(map(int, input().split()))
print(kadane_efficiency(arr))
