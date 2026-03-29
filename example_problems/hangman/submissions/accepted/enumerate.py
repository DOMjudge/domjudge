import sys

lines = [x.strip() for x in sys.stdin.readlines()]
print(chr(len(lines[0])+ord('a')))
