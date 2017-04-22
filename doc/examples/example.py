#!/usr/bin/env python2
# This shebang line above is not necessary for submission, but makes
# it easier to run your program locally.

import sys

def main():
    n = int(input())
    for i in range(n):
        name = sys.stdin.readline().rstrip('\n')
        print('Hello %s!' % (name))

if __name__ == '__main__':
    main()
