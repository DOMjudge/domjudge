#!/bin/bash

tests/syntax

make configure
./configure --with-baseurl='http://localhost/domjudge/'
make install-docs
make clean

cd doc/manual/
make version.py
sphinx-build -W -b html . build
