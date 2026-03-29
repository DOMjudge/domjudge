import sys

guess = "ZQXJKVBPGYFMWCULDRHSNIOATE"

cntr = [len(x.strip()) for x in sys.stdin.readlines()][0]
print(guess[cntr-1].lower())
