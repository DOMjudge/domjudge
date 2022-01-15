#!/usr/bin/env python3

import os
import mmap
import itertools

def domjudgePermutations():
    perm = [''.join(x) for x in itertools.permutations("DOM",3)]
    perm += ['DOm','Dom']
    fin = []
    for judge in ['Judge','judge']:
        for p in perm:
            fin.append(p+judge)
    fin.remove('DOMjudge')
    return fin

found = False
for root, dirs, files in os.walk("./"):
    for fi in files:
        if 'cache' in root:
            continue
        if '.git' in root:
            continue
        fi = os.path.join(root, fi)
        try:
            with open(fi,'r') as f:
                try:
                    s = mmap.mmap(f.fileno(), 0, access=mmap.ACCESS_READ)
                    for permutation in domjudgePermutations():
                        if permutation == 'DomJudge' and 'ChangeLog' in fi:
                            continue
                        if s.find(permutation.encode('utf-8')) != -1:
                            print('content', permutation, fi)
                            found = True
                        if permutation in fi:
                            print('filename', permutation, fi)
                            found = True
                except ValueError:
                    continue
        except FileNotFoundError:
            continue
exit(int(found))
