#!/usr/bin/env python3
# Correct solution using matrix exponentiation for large n
# For n <= 90, regular iteration works fine
# For n > 90, we use matrix exponentiation with modulo
# @EXPECTED_RESULTS@: CORRECT
# @EXPECTED_SCORE@: 100

MOD = 10**9 + 7


def matrix_mult(A, B, mod):
    return [
        [
            (A[0][0] * B[0][0] + A[0][1] * B[1][0]) % mod,
            (A[0][0] * B[0][1] + A[0][1] * B[1][1]) % mod,
        ],
        [
            (A[1][0] * B[0][0] + A[1][1] * B[1][0]) % mod,
            (A[1][0] * B[0][1] + A[1][1] * B[1][1]) % mod,
        ],
    ]


def matrix_pow(M, n, mod):
    result = [[1, 0], [0, 1]]  # Identity matrix
    while n > 0:
        if n % 2 == 1:
            result = matrix_mult(result, M, mod)
        M = matrix_mult(M, M, mod)
        n //= 2
    return result


def fibonacci(n):
    if n <= 1:
        return n
    if n <= 90:
        # Simple iteration for small n
        a, b = 0, 1
        for _ in range(n - 1):
            a, b = b, (a + b) % MOD
        return b
    else:
        # Matrix exponentiation for large n, with modulo
        M = [[1, 1], [1, 0]]
        result = matrix_pow(M, n, MOD)
        return result[0][1]


n = int(input())
print(fibonacci(n))
