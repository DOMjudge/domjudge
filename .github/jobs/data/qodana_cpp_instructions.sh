#!/usr/bin/env bash

make configure
CPP="/usr/bin/clang-19 -E"
CC=/usr/bin/clang-19
CXX=/usr/bin/clang++-19
export CC
export CPP
export CXX
./configure --with-domjudge-user=qodana

compiledb make judgehost

cat compile_commands.json
