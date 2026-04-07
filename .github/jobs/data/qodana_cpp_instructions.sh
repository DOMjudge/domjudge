#!/usr/bin/env bash

apt-get update -y
apt-get install -y --no-install-recommends bear libc++-19-dev libc++abi-19-dev
autoreconf -fi
#bear -- make || true

make configure
CPP="/usr/bin/clang-19 -E"
CC=/usr/bin/clang-19
CXX="/usr/bin/clang++-19 -stdlib=libc++"
export CC
export CPP
export CXX
./configure --with-domjudge-user=qodana

compiledb make judgehost

cat compile_commands.json
