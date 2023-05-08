'''
Sample solution in Python for the "boolfind" interactive problem.

This waits for one more testcase which is not specified.

@EXPECTED_RESULTS@: TIMELIMIT
'''

import sys

ncases = int(sys.stdin.readline())
for i in range(ncases+1):
	n = int(sys.stdin.readline())
	lo = 0
	hi = n
	while (lo+1 < hi):
		mid = (lo+hi)//2
		print(f"READ {mid}")
		answer = input()
		if (answer == "true"):
			lo = mid
		elif answer=="false":
			hi = mid
		else:
			raise Exception(f"invalid return value '{answer}'")
	print(f"OUTPUT {lo}")
