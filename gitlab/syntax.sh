#!/bin/bash

. gitlab/dind.profile

section_start syntax_check "Check unbuild files"
tests/syntax
section_end syntax_check

section_start configure "Build the docs"
make configure
./configure --with-baseurl='http://localhost/domjudge/'
make install-docs
make clean
section_end configure

section_start sphinx "Sphinx build"
cd doc/manual/
make version.py
./gen_conf_ref.py
sphinx-build -b html . build
section_end sphinx
