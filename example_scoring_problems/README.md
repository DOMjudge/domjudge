# Scoring Example Problems

This directory contains example problems for testing **scoring-type contests**
(also known as partial scoring or IOI-style scoring).

## Problems

### A - Add One (60 points total)
A simple problem: read an integer and output it plus one.

Test groups:
- **small** (10 points): Small numbers (n <= 100)
- **medium** (20 points): Medium numbers (n <= 10^6)
- **large** (30 points): Large numbers (n <= 10^18)

### B - Fibonacci Number (100 points total)
Compute the n-th Fibonacci number.

Test groups:
- **basic** (25 points): n <= 20
- **medium** (25 points): n <= 45
- **hard** (25 points): n <= 90
- **extreme** (25 points): n <= 10^6 (requires matrix exponentiation, modulo 10^9+7)

### C - Largest Sum (100 points total)
Find the maximum sum of a contiguous subarray (classic Kadane's algorithm problem).

Test groups:
- **basic** (20 points): Simple test cases
- **efficiency** (30 points, averaged): Tests algorithm efficiency
  - small (15 points), medium (30 points), large (45 points)
- **robustness** (50 points, min - all must pass): Edge cases
  - negative numbers, single element, overflow handling

### D - Knapsack (0-100 points)
Classic 0/1 knapsack optimization problem. Uses a custom output validator that
scores solutions based on the total value achieved compared to the optimal.

Test groups:
- **main** (0-100 points): Score determined by custom validator based on solution quality

## Contest Configuration

The scoring contest uses `scoreboard-type: score` instead of the default
pass-fail scoring. Points are accumulated from all problems.

## Installation

To install these scoring examples:

```bash
dj_setup_database install-scoring-examples
```

Or import manually using the DOMjudge web interface.
