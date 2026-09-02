#!/usr/bin/env bash

#apt-get update -y
#apt-get purge -y libc++*16*; clang-16* llvm-16*
#apt-get install -y --no-install-recommends bear libc++-19-dev libc++abi-19-dev clang-19 libcgroup-dev libcgroup-dev
#autoreconf -fi

unset CPLUS_INCLUDE_PATH
make configure

#CC=/usr/bin/clang-19
#CPP="/usr/bin/clang-19 -E"
#CXX=/usr/bin/clang++-19
#CXXFLAGS="-std=c++20 -nostdinc++ -isystem /usr/lib/llvm-19/include/c++/v1 -isystem /usr/lib/llvm-19/lib/clang/19/include -isystem /usr/include/x86_64-linux-gnu -isystem /usr/include"
#LDFLAGS="-stdlib=libc++ -L/usr/lib/llvm-19/lib"
#export CC
#export CPP
#export CXX
#export CXXFLAGS
#export LDFLAGS
#CC=/usr/bin/x86_64-linux-gnu-gcc-13 CXX=/usr/bin/x86_64-linux-gnu-g++-13 
./configure --with-domjudge-user=qodana

#    1  ls
#    2  apt update; apt install gcc g++
#    3  cd /code/
#    4  ./configure --with-domjudge-user=qodana
#    5  apt update; apt install gcc g++ libcgroup-dev
#    6  ./configure --with-domjudge-user=qodana
#    7  compiledb make judgehost
#    8  apt search compiledb
#    9  pip3 install compiledb --break-system-packages
#   10  find / -name pip3
#   11  find / -name pip3 2>/dev/zero
#   12  apt update; apt install gcc g++ libcgroup-dev python3-pip
#   13  pip3 install compiledb --break-system-packages
#   14  compiledb make judgehost
#   15  find / -name gcc
#   16  CC=/usr/bin/gcc ./configure --with-domjudge-user=qodana; compiledb make judgehost
#   17  CC=/usr/bin/gcc ./configure --with-domjudge-user=qodana; compiledb make judgehost
#   18  CXX=/usr/bin/g++ ./configure --with-domjudge-user=qodana; compiledb make judgehost
#   19  CC=/usr/bin/gcc CXX=/usr/bin/g++ ./configure --with-domjudge-user=qodana; compiledb make judgehost
#   20  gcc -version
#   21  gcc --version
#   22  apt search gcc
#   23  apt search gcc-13
#   24  apt search gcc-12
#   25  gcc --version
#   26  apt search gcc-13
#   27  cat /etc/apt/sources.list.d/debian.sources 
#   28  cat /etc/apt/sources.list.d/llvm.list 
#   29  CC=/usr/bin/gcc CXX=/usr/bin/g++ ./configure --with-domjudge-user=qodana; compiledb make judgehost
#   30  vim /etc/apt/sources.list.d/debian.sources 
#   31  apt install vim
#   32  vim /etc/apt/sources.list.d/debian.sources 
#   33* vim /etc/apt/preferences.d/trixie
#   34  apt update
#   35  apt list --upgradable
#   36  mv /etc/apt/preferences.d/trixie{.,}
#   37  vim /etc/apt/preferences.d/trixie 
#   38  apt install gcc-13
#   39  vim /etc/apt/preferences.d/trixie 
#   40  apt install gcc-13
#   41  gcc --version
#   42  find / -name "*gcc*" -type f -executable
#   43  find / -name "*gcc*" -type f -executable 2>/dev/zero
#   44  /usr/bin/x86_64-linux-gnu-gcc-13 --version
#   45  CC=/usr/bin/x86_64-linux-gnu-gcc-13 CXX=/usr/bin/g++ ./configure --with-domjudge-user=qodana; compiledb make judgehost
#   46  apt install g++-13
#   47  find / -name "*g++-13*" -type f -executable 2>/dev/zero
#   48  CC=/usr/bin/x86_64-linux-gnu-gcc-13 CXX=/usr/bin/x86_64-linux-gnu-g++-13 ./configure --with-domjudge-user=qodana; compiledb make judgehost
#   49  CC=/usr/bin/x86_64-linux-gnu-gcc-13 CXX=/usr/bin/x86_64-linux-gnu-g++-13 ./configure --with-domjudge-user=qodana; compiledb make judgehost 2>&1 | less
#   50  apt search less
#   51  CC=/usr/bin/x86_64-linux-gnu-gcc-13 CXX=/usr/bin/x86_64-linux-gnu-g++-13 ./configure --with-domjudge-user=qodana; compiledb make judgehost 2>&1 | more
#   52  CC=/usr/bin/x86_64-linux-gnu-gcc-13 CXX=/usr/bin/x86_64-linux-gnu-g++-13 ./configure --with-domjudge-user=qodana
#   53  echo | g++-13 -v -x c++ -
#   54  apt remove libc++-16-dev libc++abi-16-dev
#   55  CC=/usr/bin/x86_64-linux-gnu-gcc-13 CXX=/usr/bin/x86_64-linux-gnu-g++-13 ./configure --with-domjudge-user=qodana
#   56  compiledb make judgehost
#   57  compiledb make judgehost | more
#   58  compiledb make judgehost 2>&1| more
#   59  echo | g++-13 -v -x c++ -
#   60  ls -l /usr/include/c++ | grep v1
#   61  ls -l /usr/local/include
#   62  env | grep -E 'CPLUS_INCLUDE_PATH|C_INCLUDE_PATH|CPATH'
#   63  unzet CPLUS_INCLUDE_PATH
#   64  unset CPLUS_INCLUDE_PATH
#   65  env | grep -E 'CPLUS_INCLUDE_PATH|C_INCLUDE_PATH|CPATH'
#   66  CC=/usr/bin/x86_64-linux-gnu-gcc-13 CXX=/usr/bin/x86_64-linux-gnu-g++-13 ./configure --with-domjudge-user=qodana; compiledb make judgehost
#   67  history


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
