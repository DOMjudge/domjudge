#!/usr/bin/env bats

load 'assert'

u="domjudge-bats-user"

distro_id=$(grep "^ID=" /etc/os-release)

cmd="apt-get"
if [ "$distro_id" = "ID=fedora" ]; then
    cmd=dnf
fi

translate () {
    args="$@"
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
    if [ "$distro_id" = "ID=fedora" ]; then
        repo-install httpd
    fi
    repo-install gcc g++ libcgroup-dev composer
}

run_configure () {
    su $u -c "./configure $*"
}

repo-install () {
    args=$(translate $@)
    ${cmd} install $args -y >/dev/null
}
repo-remove () {
    args=$(translate $@)
    ${cmd} remove $args -y #>/dev/null
    if [ "$distro_id" != "ID=fedora" ]; then
        apt-get autoremove -y 2>/dev/null
    fi
}

@test "Default empty configure" {
    # cleanup from earlier runs
    repo-remove gcc g++ clang
    run ./configure
    assert_failure 1
    assert_line "checking whether configure should try to set CFLAGS... yes"
    assert_line "checking whether configure should try to set CXXFLAGS... yes"
    assert_line "checking whether configure should try to set LDFLAGS... yes"
    assert_line "checking for gcc... no"
    assert_line "checking for cc... no"
    assert_line "checking for cl.exe... no"
    assert_regex "configure: error: in .${test_path}':"
    assert_line 'configure: error: no acceptable C compiler found in $PATH'
    assert_regex "See [\`']config.log' for more details"
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
    assert_line " * CXXFLAGS............: -g -O2 -Wall -Wformat -Wformat-security -pedantic -fstack-protector -fPIE -D_FORTIFY_SOURCE=2 -std=c++11"
    assert_line " * LDFLAGS.............:  -fPIE -pie -Wl,-z,relro -Wl,-z,now"
}

@test "Install GNU C only" {
    repo-remove clang g++
    repo-install gcc libcgroup-dev
    compiler_assertions gcc ''
    assert_line "checking for gcc... gcc"
    assert_line "checking whether gcc accepts -g... yes"
    assert_line "configure: error: C++ preprocessor \"/lib/cpp\" fails sanity check"
}

@test "Install GNU C++ only" {
    # This does work due to dependencies
    repo-remove clang gcc
    repo-install g++ libcgroup-dev
    compiler_assertions gcc g++
    assert_line "checking for gcc... gcc"
    assert_line "checking for g++... g++"
    compile_assertions_finished
}

@test "Install C/C++ compilers (Clang as alternative)" {
    if [ "$distro_id" = "ID=fedora" ]; then
        # Fedora has gcc as dependency for clang
        skip
    fi
    repo-remove gcc g++
    repo-install clang libcgroup-dev
    compiler_assertions cc c++
    assert_line "checking for gcc... no"
    assert_line "checking for cc... cc"
    assert_line "checking for g++... no"
    assert_line "checking for c++... c++"
    compile_assertions_finished
}

@test "Check for missing webserver group" {
    if [ "$distro_id" != "ID=fedora" ]; then
        # Debian/Ubuntu start with a www-data group
        skip
    fi
    repo-remove httpd nginx
    for www_group in nginx apache; do
        userdel ${www_group} || true
        groupdel ${www_group} || true
    done
    run ./configure --with-domjudge-user=$u
    assert_line "checking webserver-group... configure: error: webserver group could not be detected, use --with-webserver-group=GROUP"
}

@test "Check for newly added webserver group (Apache)" {
    if [ "$distro_id" != "ID=fedora" ]; then
        # Debian/Ubuntu start with a www-data group
        skip
    fi
    repo-remove httpd nginx
    for www_group in nginx apache; do
        userdel ${www_group} || true
        groupdel ${www_group} || true
    done
    repo-install httpd
    run ./configure --with-domjudge-user=$u
    assert_line "checking webserver-group... apache (detected)"
    assert_line " * webserver group.....: apache"
}

@test "Check for newly added webserver group (Nginx)" {
    if [ "$distro_id" != "ID=fedora" ]; then
        # Debian/Ubuntu start with a www-data group
        skip
    fi
    repo-remove httpd nginx
    for www_group in nginx apache; do
        userdel ${www_group} || true
        groupdel ${www_group} || true
    done
    repo-install nginx
    run ./configure --with-domjudge-user=$u
    assert_line "checking webserver-group... nginx (detected)"
    assert_line " * webserver group.....: nginx"
}

@test "cgroup library needed" {
   cgroup_init_find="checking for cgroup_init in -lcgroup... no"
   cgroup_init_error="configure: error: Linux cgroup library not found."
   setup_user
   repo-install gcc g++
   repo-remove libcgroup-dev
   run run_configure
   assert_line "$cgroup_init_find"
   assert_line "$cgroup_init_error"
   repo-install libcgroup-dev
   run run_configure
   refute_line "$cgroup_init_find"
   refute_line "$cgroup_init_error"
}
