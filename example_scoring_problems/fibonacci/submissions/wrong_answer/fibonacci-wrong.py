#!/usr/bin/env python3
# Wrong solution - off-by-one error
# @EXPECTED_RESULTS@: WRONG-ANSWER
# @EXPECTED_SCORE@: 0
def fibonacci(n):
    if n <= 1:
        return 1  # Wrong: should be n
    a, b = 1, 1  # Wrong starting values
    for _ in range(n - 1):
        a, b = b, a + b
    return b

n = int(input())
print(fibonacci(n))
