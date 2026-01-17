#!/usr/bin/env python3
# Handles basic, efficiency/small, and efficiency/medium correctly
# Intentionally produces wrong answers for n > 5000
# basic (20) + efficiency avg(15, 30, 0) = 15 + robustness min(50, 50, 50) = 50
# @EXPECTED_SCORE@: 85

def kadane(arr):
    max_sum = arr[0]
    curr_sum = arr[0]
    for i in range(1, len(arr)):
        curr_sum = max(arr[i], curr_sum + arr[i])
        max_sum = max(max_sum, curr_sum)
    return max_sum

n = int(input())
arr = list(map(int, input().split()))

if n > 5000:
    # Wrong answer for large inputs
    print(0)
else:
    print(kadane(arr))
