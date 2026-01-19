#!/usr/bin/env python3
# Correct for basic+medium (n <= 45), but wrong for hard+extreme
# Uses simple iteration with modulo, but has a bug for n > 45
# @EXPECTED_RESULTS@: WRONG-ANSWER
# @EXPECTED_SCORE@: 50

MOD = 10**9 + 7

n = int(input())

if n <= 1:
    print(n)
else:
    a, b = 0, 1
    for _ in range(n - 1):
        a, b = b, (a + b) % MOD

    # Correct for n <= 45, but introduce a bug for larger n
    if n <= 45:
        print(b)
    else:
        print((b + 1) % MOD)  # Off by one for large n
