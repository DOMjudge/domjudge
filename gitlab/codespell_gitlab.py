#!/usr/bin/env python3

import yaml
import os,sys
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

github_to_codespell = {'ignore_words_file':'I'}

command = "codespell -q4"

with open(".github/workflows/codespell.yml", "r") as stream:
  try:
    settings = yaml.safe_load(stream)
    for githubkey,codespellkey in github_to_codespell.items():
      value = settings['jobs']['codespell']['steps'][2]['with'][githubkey]
      command += f" -{codespellkey} {value}"
  except yaml.YAMLError as exc:
    print(exc)
with open("gitlab/codespellignorefiles.txt", "r") as f:
  skip_list = ",".join(line for line in f.read().splitlines())
  command += f" -S {skip_list}"

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

sys.exit(int(found)+int(bool(os.system(command))))
