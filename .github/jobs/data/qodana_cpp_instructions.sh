#!/usr/bin/env bash

apt-get update -y
apt-get purge -y libc++*16*; clang-16* llvm-16*
apt-get install -y --no-install-recommends bear libc++-19-dev libc++abi-19-dev clang-19 libcgroup-dev libcgroup-dev
autoreconf -fi

make configure
CC=/usr/bin/clang-19
CPP="/usr/bin/clang-19 -E"
CXX=/usr/bin/clang++-19
CXXFLAGS="-std=c++20 -nostdinc++ -isystem /usr/lib/llvm-19/include/c++/v1 -isystem /usr/lib/llvm-19/lib/clang/19/include -isystem /usr/include/x86_64-linux-gnu -isystem /usr/include"
LDFLAGS="-stdlib=libc++ -L/usr/lib/llvm-19/lib"
export CC
export CPP
export CXX
export CXXFLAGS
export LDFLAGS
./configure --with-domjudge-user=qodana

#./configure --with-domjudge-user=root CC=/usr/bin/clang-19 CXX=/usr/bin/clang++-19   CXXFLAGS="-std=c++20 -nostdinc++ \
#    -isystem /usr/lib/llvm-19/include/c++/v1 \
#    -isystem /usr/lib/llvm-19/lib/clang/19/include \
#    -isystem /usr/include/x86_64-linux-gnu \
#    -isystem /usr/include"   LDFLAGS="-stdlib=libc++ -L/usr/lib/llvm-19/lib"

#    1  apt update; apt install -y libc++-19-dev libcgroup-dev; make configure; ./configure --with-domjudge-user=root CC=clang-19 CXX=clang-19++; make judgehost
#    2  dpkg -l | grep -E "llvm-16|clang-16|libc\+\+.*16|libunwind-16"
#    3  apt purge clang-16* ; apt update; apt install -y libc++-19-dev libcgroup-dev; make configure; ./configure --with-domjudge-user=root CC=clang-19 CXX=clang-19++; make judgehost
#    4  whereis clang++
#    5  ls -atrl /usr/bin/clang++
#    6  find / -name clang-19
#    7  apt purge clang-16* ; apt update; apt install -y clang-19 libcgroup-dev libc++-19-dev libcgroup-dev; make configure; ./configure --with-domjudge-user=root CC=clang-19 CXX=clang-19++; make judgehost
#    8  dpkg -l | grep -E "llvm-16|clang-16|libc\+\+.*16|libunwind-16"
#    9  apt purge clang-16* llvm-16* ; apt update; apt install -y clang-19 libcgroup-dev libc++-19-dev libcgroup-dev; make configure; ./configure --with-domjudge-user=root CC=clang-19 CXX=clang-19++; make judgehost
#   10  dpkg -l | grep -E "llvm-16|clang-16|libc\+\+.*16|libunwind-16"
#   11  apt purge -y libc++*16*; clang-16* llvm-16* ; apt update; apt install -y clang-19 libcgroup-dev libc++-19-dev libcgroup-dev; make configure; ./configure --with-domjudge-user=root CC=clang-19 CXX=clang-19++; make judgehost
#   12  dpkg -l | grep -E "llvm-16|clang-16|libc\+\+.*16|libunwind-16"
#   13  find / -name clang-19
#   14  apt purge -y libc++*16*; clang-16* llvm-16* ; apt update; apt install -y clang-19 libcgroup-dev libc++-19-dev libcgroup-dev; make configure; ./configure --with-domjudge-user=root CC=/usr/bin/clang-19 CXX=clang-19++; make judgehost
#   15  apt purge -y libc++*16*; clang-16* llvm-16* ; apt update; apt install -y clang-19 libcgroup-dev libc++-19-dev libcgroup-dev; make configure; ./configure --with-domjudge-user=root CC=/usr/bin/clang-19 CXX=/usr/bin/clang-19++; make judgehost
#   16  ./configure --with-domjudge-user=root CC=/usr/bin/clang-19 CXX=/usr/bin/clang-19++
#   17  ./configure --with-domjudge-user=root CPP="clang-19 -E" CC=/usr/bin/clang-19 CXX=/usr/bin/clang-19++
#   18  ./configure --with-domjudge-user=root CPP="clang-19 -E" CC=/usr/bin/clang-19 CXX=/usr/bin/clang++-19
#   19  apt purge -y libc++*16*; clang-16* llvm-16* ; apt update; apt install -y clang-19 libcgroup-dev libc++-19-dev libcgroup-dev; make configure; ./configure --with-domjudge-user=root CC=/usr/bin/clang-19 CXX=/usr/bin/clang++-19; make judgehost
#   20  find / -name "format" -path "*/c++/*" 2>/dev/null
#   21  apt purge -y libc++*16*; clang-16* llvm-16* ; apt update; apt install -y clang-19 libcgroup-dev libc++-19-dev libcgroup-dev; make configure; ./configure --with-domjudge-user=root CC=/usr/bin/clang-19 CXX=/usr/bin/clang++-19 CXXFLAGS="-std=c++20 -stdlib=libc++ -I/usr/lib/llvm-19/include/c++/v1/" LDFLAGS="-L/usr/lib/llvm-19/lib -lc++"; make judgehost
#   22  clang++-19 -print-resource-dir
#   23  apt purge -y libc++*16*; clang-16* llvm-16* ; apt update; apt install -y clang-19 libcgroup-dev libc++-19-dev libcgroup-dev; make configure; ./configure --with-domjudge-user=root CC=/usr/bin/clang-19 CXX=/usr/bin/clang++-19 CXXFLAGS="-std=c++20 -stdlib=libc++ -I$(clang++-19 -print-resource-dir)/include -I/usr/lib/llvm-19/include/c++/v1/" LDFLAGS="-L/usr/lib/llvm-19/lib -lc++"; make judgehost
#   24  apt purge -y libc++*16*; clang-16* llvm-16* ; apt update; apt install -y clang-19 libcgroup-dev libc++-19-dev libcgroup-dev; make configure; ./configure --with-domjudge-user=root CC=/usr/bin/clang-19 CXX=/usr/bin/clang++-19 CXXFLAGS="-std=c++20 -stdlib=libc++ -isystem /usr/lib/llvm-19/lib/clang/19/include -isystem /usr/lib/llvm-19/include/c++/v1/" LDFLAGS="-L/usr/lib/llvm-19/lib -lc++"; make judgehost
#   25  find / -name "stddef.h" 2>/dev/null
#   26  grep -l "nullptr_t" $(find / -name "stddef.h" 2>/dev/null)
#   27  apt-get install -y --reinstall libc++-19-dev libc++abi-19-dev libclang-common-19-dev
#   28  apt purge -y libc++*16*; clang-16* llvm-16* ; apt update; apt install -y clang-19 libcgroup-dev libc++-19-dev libcgroup-dev; make configure; ./configure --with-domjudge-user=root CC=/usr/bin/clang-19 CXX=/usr/bin/clang++-19 CXXFLAGS="-std=c++20 -stdlib=libc++ -isystem /usr/lib/llvm-19/lib/clang/19/include -isystem /usr/lib/llvm-19/include/c++/v1/" LDFLAGS="-L/usr/lib/llvm-19/lib -lc++"; make judgehost
#   29  ./configure --with-domjudge-user=root CC=/usr/bin/clang-19 CXX=/usr/bin/clang++-19   CXXFLAGS="-std=c++20 -nostdinc++ \
#    -isystem /usr/lib/llvm-19/include/c++/v1 \
#    -isystem /usr/lib/llvm-19/lib/clang/19/include \
#    -isystem /usr/include/x86_64-linux-gnu \
#    -isystem /usr/include"   LDFLAGS="-stdlib=libc++ -L/usr/lib/llvm-19/lib"
#   30  make judgehost
#   31  history

compiledb make judgehost

cat compile_commands.json
