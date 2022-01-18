#!/bin/bash

tests/syntax

make configure
./configure --with-baseurl='http://localhost/domjudge/'
make install-docs
make clean

cd doc/manual/
make version.py
./gen_conf_ref.py
sphinx-build -b html . build
