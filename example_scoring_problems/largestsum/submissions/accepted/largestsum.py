#!/usr/bin/env python3
# Correct solution using Kadane's algorithm
# Handles all cases: basic, efficiency (small/medium/large), and robustness (negative/single/overflow)
# @EXPECTED_SCORE@: 100

def kadane(arr):
    """Find maximum sum of contiguous subarray using Kadane's algorithm O(n)"""
    max_sum = arr[0]
    curr_sum = arr[0]
    for i in range(1, len(arr)):
        curr_sum = max(arr[i], curr_sum + arr[i])
        max_sum = max(max_sum, curr_sum)
    return max_sum

n = int(input())
arr = list(map(int, input().split()))
print(kadane(arr))
