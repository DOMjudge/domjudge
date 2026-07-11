#!/usr/bin/env bash

u="domjudge-bats-user"

distro_id=$(grep "^ID=" /etc/os-release)

if [ -z "$testsuite" ]; then
    echo 'Var: testsuite, not set.'
    exit 1
fi

cmd="apt-get"
if [ "$distro_id" = "ID=fedora" ]; then
    cmd=dnf
fi

translate () {
    args="$*"
    if [ "$distro_id" = "ID=fedora" ]; then
        args=${args/libcgroup-dev/libcgroup-devel}
    fi
    echo "$args"
}

if [ -z ${test_path+x} ]; then
    test_path="/domjudge"
    # Used in the CI
fi

setup_user() {
    id -u $u || (useradd $u ; groupadd $u || true )>/dev/null
    chown -R $u:$u ./
}

setup() {
    setup_user
    for shared_file in config.log confdefs.h conftest.err; do
        chmod a+rw $shared_file || true
    done
    echo "$testsuite"
    if [ "$distro_id" = "ID=fedora" ]; then
        if [ "$testsuite" = apache ]; then
            repo-install httpd
        else
            repo-install nginx
        fi
    fi
    if [ "$testsuite" = clang ]; then
       repo-install libcgroup-dev composer
    else
       repo-install gcc g++ libcgroup-dev composer
    fi
}

run_configure () {
    su $u -c "./configure $*"
}

composer-install () {
    repo-install curl
    curl "https://getcomposer.org/download/latest-stable/composer.phar" -o /usr/local/bin/composer
    chmod +x /usr/local/bin/composer
}

repo-install () {
    args=$(translate $@)
    if [ "$distro_id" = "ID=debian" ] && [[ "$args" == *"composer"* ]]; then
        args=${args/composer/}
        if ! apt-get install -qq -y composer; then
            composer-install
        fi
    fi
    ${cmd} install $args -y >/dev/null
}

repo-remove () {
    args=$(translate $@)
    if [ "$distro_id" = "ID=debian" ] && [[ "$args" == *"composer"* ]]; then
        args=${args/composer/}
        apt-get remove -y composer; rm -rf /usr/local/bin/composer
    fi
    ${cmd} remove $args -y #>/dev/null
    if [ "$distro_id" != "ID=fedora" ]; then
        apt-get autoremove -y 2>/dev/null
    fi
}

compiler_c_only_assertions () {
    run ./configure
    assert_failure
    assert_line "checking whether configure should try to set CXXFLAGS... yes"
    assert_line "checking whether configure should try to set LDFLAGS... yes"
    assert_line "checking for g++... no"
    assert_line "checking for c++... no"
    assert_line "checking for gpp... no"
    assert_line "checking for aCC... no"
    assert_line "checking for CC... no"
    assert_line "checking for cxx... no"
    assert_line "checking for cc++... no"
    assert_line "checking for cl.exe... no"
    assert_line "checking for FCC... no"
    assert_line "checking for KCC... no"
    assert_line "checking for RCC... no"
    assert_line "checking for xlC_r... no"
    assert_line "checking for xlC... no"
    assert_line "checking for clang++... no"
    assert_line "checking whether the C++ compiler works... no"
    assert_regex "configure: error: in .${test_path}':"
    assert_line "configure: error: C++ compiler cannot create executables"
    assert_regex "See [\`']config.log' for more details"
}

compiler_assertions () {
    run run_configure
    # Depending on where we run this we might runas wrong user or lack libraries
    # so we can't expect either success or failure.
    assert_line "checking baseurl... https://example.com/domjudge/"
    assert_line "checking whether configure should try to set CXXFLAGS... yes"
    assert_line "checking whether configure should try to set LDFLAGS... yes"
    assert_line "checking for C++ compiler default output file name... a.out"
    assert_line "checking whether we are cross compiling... no"
    assert_line "checking for suffix of object files... o"
    assert_line "checking whether the compiler supports GNU C++... yes"
    assert_line "checking whether C++ compiler accepts -Wall... yes"
    assert_line "checking whether C++ compiler accepts -Wformat... yes"
    assert_line "checking whether C++ compiler accepts -Wformat-security... yes"
    assert_line "checking whether C++ compiler accepts -pedantic... yes"
    assert_line "checking whether C++ compiler accepts -fstack-protector... yes"
    assert_line "checking whether C++ compiler accepts -fPIE... yes"
    assert_line "checking whether C++ compiler accepts -D_FORTIFY_SOURCE=2... yes"
    assert_regex "checking whether .*GNU C.*\.\.\. yes"
    assert_line "checking whether the linker accepts -fPIE... yes"
    assert_line "checking whether the linker accepts -pie... yes"
    assert_line "checking whether the linker accepts -Wl,-z,relro... yes"
    assert_line "checking whether the linker accepts -Wl,-z,now... yes"
    assert_line "checking whether $1 accepts -g... yes"
    assert_regex "^checking for $1 option to (enable C11 features|enable C23 features|accept ISO C89)\.\.\. (none needed|-std=gnu23)$"
    if [ -n "$2" ]; then
        assert_line "checking whether $2 accepts -g... yes"
        assert_regex "^checking how to run the C preprocessor... $1( -std=gnu23|) -E$"
        assert_line "checking how to run the C++ preprocessor... $2 -E"
    fi
}

compile_assertions_finished () {
    assert_line "checking whether the C++ compiler works... yes"
    assert_line " * CXXFLAGS............: -g -O2 -Wall -Wformat -Wformat-security -pedantic -fstack-protector -fPIE -D_FORTIFY_SOURCE=2 -std=c++20"
    assert_line " * LDFLAGS.............:  -fPIE -pie -Wl,-z,relro -Wl,-z,now"
}
