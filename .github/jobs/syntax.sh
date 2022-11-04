#!/bin/bash

set -euo pipefail

MYDIR=$(dirname "$0")

$MYDIR/syntax-check

make configure
./configure --with-baseurl='http://localhost/domjudge/' --with-domjudge-user=root
make install-docs
make clean

cd doc/manual/
make version.py
./gen_conf_ref.py
sphinx-build -b html . build
