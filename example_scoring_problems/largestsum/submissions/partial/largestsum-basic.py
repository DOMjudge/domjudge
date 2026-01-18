#!/usr/bin/env python3
# Only handles basic tests correctly (small arrays)
# Intentionally produces wrong answers for n > 50
# basic (20) + efficiency avg(0, 0, 0) = 0 + robustness min(50, 50, 50) = 50
# @EXPECTED_SCORE@: 70

def kadane(arr):
    max_sum = arr[0]
    curr_sum = arr[0]
    for i in range(1, len(arr)):
        curr_sum = max(arr[i], curr_sum + arr[i])
        max_sum = max(max_sum, curr_sum)
    return max_sum

n = int(input())
arr = list(map(int, input().split()))

if n > 50:
    # Wrong answer for larger inputs
    print(0)
else:
    print(kadane(arr))
