# This is used instead of version.py when generating the manual from
# `make dist' when paths.mk is not yet available.

# Keep this in sync with the command in configure.ac to extract the version.
import os
release = os.popen("grep 'version' ../../README.md | sed -n '1s/^.*version //p' | tr -d '\n'").read()

# The short X.Y version
version = release.rsplit('.',1)[0]

project = 'DOMjudge'
