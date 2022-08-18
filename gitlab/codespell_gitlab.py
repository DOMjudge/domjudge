import yaml
import os,sys

github_to_codespell = {'ignore_words_file':'I'}

command = "codespell -q4"

with open(".github/workflows/codespell.yml", "r") as stream:
  try:
    settings = yaml.safe_load(stream)
    for githubkey,codespellkey in github_to_codespell.items():
      for github_step in settings['jobs']['codespell']['steps']:
        try:
          value = github_step['with'][githubkey]
          command += f" -{codespellkey} {value}"
          break
        except KeyError:
          continue
  except yaml.YAMLError as exc:
    print(exc)
with open("gitlab/codespellignorefiles.txt", "r") as f:
  skip_list = ",".join(line for line in f.read().splitlines())
  command += f" -S {skip_list}"

sys.exit(int(bool(os.system(command))))
