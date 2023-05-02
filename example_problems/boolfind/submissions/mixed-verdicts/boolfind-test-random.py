'''
Sample solution in Python for the "boolfind" interactive problem.
This will sometimes give an off by one answer. This is a demonstration
of how to use the @EXPECTED_RESULTS@: tag.

@EXPECTED_RESULTS@: CORRECT, WRONG-ANSWER
'''

import sys, random

ncases = int(sys.stdin.readline())
for i in range(ncases):
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
	if bool(random.getrandbits(1)):
		print(f"OUTPUT {lo}")
	else:
		print(f"OUTPUT {lo+1}")
