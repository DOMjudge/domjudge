#!/usr/bin/bash

u="domjudge-bats-user"

distro_id=$(grep "^ID=" /etc/os-release)

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

repo-install () {
    args=$(translate "$@")
    # shellcheck disable=SC2086
    ${cmd} install $args -y >/dev/null
}
repo-remove () {
    args=$(translate "$@")
    # shellcheck disable=SC2086
    ${cmd} remove $args -y #>/dev/null
    if [ "$distro_id" != "ID=fedora" ]; then
        apt-get autoremove -y 2>/dev/null
    fi
}

compiler_assertions () {
    run run_configure
    # Depending on where we run this we might runas wrong user or lack libraries
    # so we can't expect either success or failure.
    assert_line "checking baseurl... https://example.com/domjudge/"
    assert_line "checking whether configure should try to set CFLAGS... yes"
    assert_line "checking whether configure should try to set CXXFLAGS... yes"
    assert_line "checking whether configure should try to set LDFLAGS... yes"
    assert_line "checking whether the C compiler works... yes"
    assert_line "checking for C compiler default output file name... a.out"
    assert_line "checking for suffix of executables... "
    assert_line "checking whether we are cross compiling... no"
    assert_line "checking for suffix of object files... o"
    assert_regex "checking whether .*GNU C.*\.\.\. yes"
    assert_line "checking whether C compiler accepts -Wall... yes"
    assert_line "checking whether C compiler accepts -fstack-protector... yes"
    assert_line "checking whether C compiler accepts -fPIE... yes"
    assert_line "checking whether C compiler accepts -D_FORTIFY_SOURCE=2... yes"
    assert_line "checking whether the linker accepts -fPIE... yes"
    assert_line "checking whether the linker accepts -pie... yes"
    assert_line "checking whether the linker accepts -Wl,-z,relro... yes"
    assert_line "checking whether the linker accepts -Wl,-z,now... yes"
    assert_line "checking whether $1 accepts -g... yes"
    assert_regex "^checking for $1 option to (enable C11 features|accept ISO C89)\.\.\. none needed$"
    assert_line "checking whether $1 accepts -g... (cached) yes"
    if [ -n "$2" ]; then
        assert_line "checking whether $2 accepts -g... yes"
        assert_line "checking how to run the C preprocessor... $1 -E"
        assert_line "checking how to run the C++ preprocessor... $2 -E"
    fi
}

compile_assertions_finished () {
    assert_line " * CFLAGS..............: -g -O2 -Wall -Wformat -Wformat-security -pedantic -fstack-protector -fPIE -D_FORTIFY_SOURCE=2 -std=c11"
    assert_line " * CXXFLAGS............: -g -O2 -Wall -Wformat -Wformat-security -pedantic -fstack-protector -fPIE -D_FORTIFY_SOURCE=2 -std=c++20"
    assert_line " * LDFLAGS.............:  -fPIE -pie -Wl,-z,relro -Wl,-z,now"
}
