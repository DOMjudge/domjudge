import sys
import random

lines = [x.strip() for x in sys.stdin.readlines()]
possible = set([chr(ord('a')+x) for x in range(26)])
seen = set(lines[0])

pick = list(possible-seen)
random.shuffle(pick)
print(pick[0])
