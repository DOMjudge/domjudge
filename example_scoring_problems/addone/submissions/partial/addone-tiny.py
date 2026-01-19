#!/usr/bin/env python3
# Only handles small numbers (n < 100)
# Artificially limited to demonstrate partial scoring
# @EXPECTED_RESULTS@: WRONG-ANSWER
# @EXPECTED_SCORE@: 10

n = int(input())
if n < 100:
    print(n + 1)
else:
    # Deliberately wrong for larger numbers
    print(-1)
