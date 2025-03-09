#!/usr/bin/env bats

load 'assert'

u="domjudge-bats-user"
distro_id=$(grep "^ID=" /etc/os-release)

cmd="apt-get"
if [ "$distro_id" = "ID=alpine" ]; then
    cmd=apk
elif [ "$distro_id" = "ID=arch" ]; then
    cmd=pacman
elif [ "$distro_id" = "ID=fedora" ]; then
    cmd=dnf
elif [ "$distro_id" = 'ID="opensuse-leap"' ]; then
    cmd=zypper
fi

if [ -z ${test_path+x} ]; then
    test_path="/domjudge"
    # Used in the CI
fi

before_files=$(find . -type f)

# Helper functions
translate () {
    args="$*"
    if [ "$distro_id" = "ID=arch" ]; then
        args=${args/g++/}
        args=${args/libcgroup-dev/libcgroup}
    fi
    if [ "$distro_id" = 'ID="opensuse-leap"' ]; then
        args=${args/g++/gcc-c++}
        args=${args/libcgroup-dev/libcgroup-devel}
        args=${args/python3-sphinx/python3-Sphinx}
        args=${args/python3-sphinx-rtd-theme/python3-sphinx_rtd_theme}
        args=${args/python3-yaml/python3-PyYAML}
        args=${args/texgyre/texlive-tex-gyre}
        args=${args/texlive-latex-extra/texlive-collection-latexextra}
        args=${args/texlive-latex-recommended/texlive-collection-latexrecommended}
    fi
    if [ "$distro_id" = "ID=fedora" ]; then
        args=${args/libcgroup-dev/libcgroup-devel}
        args=${args/tex-gyre/texlive-tex-gyre}
        args=${args/python3-sphinx-rtd-theme/python3-sphinx_rtd_theme}
        # Debian/Ubuntu need 2 packages, fedora some more. As we always install those together we just
        # add them as replacement for and skip the other.
        args=${args/texlive-latex-recommended/texlive-latex texlive-cmap texlive-metafont texlive-ec texlive-tex-gyre texlive-fncychap texlive-wrapfig texlive-capt-of texlive-framed texlive-upquote texlive-needspace texlive-tabulary texlive-parskip texlive-oberdiek texlive-makeindex}
        args=${args/texlive-latex-extra/}
    fi
    echo "$args"
}

repo-install () {
    args=$(translate "$@")
    if [ "$distro_id" = "ID=alpine" ]; then
        # shellcheck disable=SC2086
        ${cmd} add $args &>/dev/null
    elif [ "$distro_id" = "ID=arch" ]; then
        # shellcheck disable=SC2086
        ${cmd} -Sy --noconfirm $args >/dev/null
    else
        # shellcheck disable=SC2086
        ${cmd} install -y $args
	# >/dev/null
    fi
}

repo-remove () {
    args=$(translate "$@")
    if [ "$distro_id" = "ID=alpine" ]; then
        # shellcheck disable=SC2086
        ${cmd} del $args &>/dev/null
    elif [ "$distro_id" = "ID=arch" ]; then
        # shellcheck disable=SC2086
        for arg in $args; do
            ${cmd} -Rcns "$arg" &>/dev/null || echo "$arg not found (or not removed)"
        done
    elif [ "$distro_id" = 'ID="opensuse-leap"' ]; then
        # shellcheck disable=SC2086
        ${cmd} remove -y $args &>/dev/null || ret="$?"
        if [ "$ret" -ne "104" ]; then
           return $?
        fi
    else
        # shellcheck disable=SC2086
        ${cmd} remove -y $args &>/dev/null
    fi
    if [ "$distro_id" = "ID=debian" ] || [ "$distro_id" = "ID=ubuntu" ]; then
        apt-get autoremove -y &>/dev/null
    fi
}

docs_required_install () {
    repo-install python3-sphinx-rtd-theme python3-sphinx rst2pdf tex-gyre latexmk texlive-latex-recommended texlive-latex-extra
}

run_configure () {
    su $u -c "./configure $*"
}

compiler_assertions () {
    # This test is ran with multiple containers, as we only care for the C/C++ test
    # a bogus value is provided for the webserver as for example arch doesn't have
    # a default www-data like group.
    run run_configure --with-webserver-group=not_all_containers_have_www_group
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

setup_user() {
    uadd=useradd
    gadd=groupadd
    if [ "$distro_id" = "ID=alpine" ]; then
        uadd=adduser
        gadd=addgroup
    fi
    id -u $u >/dev/null || (${uadd} $u ; ${gadd} $u || true )>/dev/null
    chown -R $u:$u ./
}

setup_helper() {
    setup_user
    for shared_file in config.log confdefs.h conftest.err; do
        chmod a+rw $shared_file &>/dev/true || true
    done
    if [ "$distro_id" = "ID=fedora" ]; then
        repo-install httpd
    fi
    if [ "$distro_id" = 'ID="opensuse-leap"' ]; then
        repo-install apache2
    fi
    repo-install gcc g++ libcgroup-dev composer acl
}

multiple_compilers() {
    # all of the tests ran before will have a subset of thos already
    repo-install gcc g++ clang libcgroup-dev
    export CC="$1"
    export CXX="$2"
    compiler_assertions "$1" "$2"
    assert_line "checking for gcc... $1"
    assert_line "checking how to run the C preprocessor... $1 -E"
    assert_line "checking how to run the C++ preprocessor... $2 -E"
    assert_line " * CPP.................: $1 -E"
    assert_line " * CXX.................: $2"
    compile_assertions_finished
}

check_docs () {
    doc_files="domjudge-team-manual.pdf html/index.html team/domjudge-team-manual.pdf"
    if [ -z "$1" ] || [ "$1" = "before" ]; then
        for f in $doc_files; do
            run ls "doc/manual/build/$f"
            assert_failure
        done
    fi
    if [ -z "$1" ]; then
        run make docs
    fi
    if [ -z "$1" ] || [ "$1" = "after" ]; then
        for f in $doc_files; do
            run ls "doc/manual/build/$f"
            assert_success
        done
    fi
}

build_default() {
    if [ "$distro_id" = "ID=fedora" ]; then
        # In the current fedora container it fails with:
        #/usr/bin/ld: cannot find -lstdc++: No such file or directory
        #/usr/bin/ld: cannot find -lm: No such file or directory
        #/usr/bin/ld: cannot find -lc: No such file or directory
        # even though those libraries are installed and work on a normal
        # fedora install.
        skip
    fi
    user_make="$1"
    user_install="$2"
    if [ -z "$3" ]; then
        prefix="/opt/domjudge"
    else
        prefix="$3"
    fi
    make_target="$4"
    setup_helper
    run run_configure --prefix="$prefix"
    assert_line " * domserver...........: $prefix/domserver"
    assert_regex "^ \* webserver group\.\.\.\.\.: (www-data|apache|nginx)$"
    assert_line " * judgehost...........: $prefix/judgehost"
    assert_line " * runguard group......: domjudge-run"
    if [ -z "$make_target" ]; then
        if [ "$user_make" = "root" ]; then
            run make domserver
        else
            run su "$user_make" -c "make domserver"
        fi
        assert_success
        if [ "$user_make" = "root" ]; then
            run make judgehost
        else
            run su "$user_make" -c "make judgehost"
        fi
    else
        if [ "$make_target" = "all" ]; then
            check_docs "before"
            run make "$make_target"
            check_docs "after"
        fi
    fi
    assert_success
    if [ "$user_install" = "root" ]; then
        run make install-domserver
    else
        run su "$user_install" -c "make install-domserver"
    fi
    assert_success
    run ls "${prefix}"/domserver/webapp
    assert_success
    if [ "$user_install" = "root" ]; then
        run make install-judgehost
    else
        run su "$user_install" -c "make install-judgehost"
    fi
    assert_success
    run ls "${prefix}"/judgehost/judgings
    assert_success
}

@test "Default empty configure" {
    # In the default image none of these tools were installed for:
    # arch, alpine, opensuse, fedora, debian & ubuntu
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
    # shellcheck disable=SC2016
    assert_line 'configure: error: no acceptable C compiler found in $PATH'
    assert_regex "See [\`']config.log' for more details"
}

# All checks need the user already
setup_user

@test "cgroup library needed" {
    if [ "${ghtest:?}" != "libcgroup" ]; then
        skip
    fi
    cgroup_init_find="checking for cgroup_init in -lcgroup... no"
    cgroup_init_error="configure: error: Linux cgroup library not found."
    setup_user
    repo-install gcc g++
    repo-remove libcgroup-dev
    run run_configure --with-webserver-group=not_every_container_has_www_group
    assert_line "$cgroup_init_find"
    assert_line "$cgroup_init_error"
    repo-install libcgroup-dev
    run run_configure
    refute_line "$cgroup_init_find"
    refute_line "$cgroup_init_error"
}

@test "Install GNU C only (and libraries)" {
    if [ "${ghtest:?}" != "gcc" ]; then
        skip
    fi
    if [ "$distro_id" = "ID=fedora" ]; then
        # Fedora ships with a gcc with enough C++ support
        skip
    fi
    repo-remove clang g++
    repo-install gcc libcgroup-dev
    compiler_assertions gcc ''
    assert_line "checking for gcc... gcc"
    assert_line "checking whether gcc accepts -g... yes"
    assert_line "configure: error: C++ preprocessor \"/lib/cpp\" fails sanity check"
}

@test "Install GNU C++ only" {
    if [ "${ghtest:?}" != "g++" ]; then
        skip
    fi
    # This does work due to dependencies
    repo-remove clang gcc
    repo-install g++ libcgroup-dev
    compiler_assertions gcc g++
    assert_line "checking for gcc... gcc"
    assert_line "checking for g++... g++"
    compile_assertions_finished
}

@test "Install C/C++ compilers (Clang as alternative)" {
    if [ "${ghtest:?}" != "clang" ]; then
        skip
    fi
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

@test "Select gcc/g++ (multiple installed)" {
    # all of the above tests will have a subset of this already
    repo-install gcc g++ clang libcgroup-dev
    compiler_assertions gcc g++
    assert_line "checking for gcc... gcc"
    assert_line "checking for g++... g++"
    compile_assertions_finished
}

@test "Select clang alternative [clang/clang] (multiple installed)" {
    multiple_compilers clang clang
}

@test "Select clang alternative [cc/c++] (multiple installed)" {
    multiple_compilers cc c++
}

@test "Select clang alternative [gcc/g++] (multiple installed)" {
    multiple_compilers gcc g++
}

@test "Select clang alternative [clang/g++] (multiple installed)" {
    multiple_compilers clang g++
}

@test "Select clang alternative [gcc/clang] (multiple installed)" {
    multiple_compilers gcc clang
}

@test "Check for missing webserver group" {
    # Make sure to not put this test after a test which runs the setup_helper
    if [ "$distro_id" != "ID=fedora" ]; then
        # Debian/Ubuntu start with a www-data group
        skip
    fi
    repo-remove httpd nginx
    for www_group in nginx apache www wwwrun; do
        userdel ${www_group} || true
        groupdel ${www_group} || true
    done
    run ./configure --with-domjudge-user=$u
    assert_line "checking webserver-group... configure: error: webserver group could not be detected, use --with-webserver-group=GROUP"
    run ./configure --with-domjudge-user=$u --with-webserver-group=root
    assert_failure
}

@test "Check for newly added webserver group (Apache)" {
    # Just use the testname of a compiler test, to not waste CI time
    if [ "${ghtest:?}" != "gcc" ]; then
        skip
    fi
    if [ "$distro_id" != "ID=fedora" ]; then
        # Debian/Ubuntu start with a www-data group
        skip
    fi
    repo-remove httpd nginx
    for www_group in nginx apache www wwwrun; do
        userdel ${www_group} || true
        groupdel ${www_group} || true
    done
    repo-install httpd
    run ./configure --with-domjudge-user=$u
    assert_line "checking webserver-group... apache (detected)"
    assert_line " * webserver group.....: apache"
}

@test "Check for newly added webserver group (Nginx)" {
    # Just use the testname of a compiler test, to not waste CI time
    if [ "${ghtest:?}" != "g++" ]; then
        skip
    fi
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

@test "Run as root discouraged" {
    setup_helper
    run su root -c "./configure"
    discourage_root="checking domjudge-user... configure: error: installing/running as root is STRONGLY DISCOURAGED, use --with-domjudge-user=root to override."
    assert_line "$discourage_root"
    run su root -c "./configure --with-domjudge-user=root"
    refute_line "$discourage_root"
    assert_line "checking domjudge-user... root"
    run su root -c "./configure --with-domjudge-user=$u"
    refute_line "$discourage_root"
    assert_line "checking domjudge-user... $u"
}

@test "Run as normal user" {
    setup_helper
    run ./configure --with-domjudge-user=$u
    assert_line "checking domjudge-user... $u"
    run su $u -c "./configure"
    assert_line "checking domjudge-user... $u (default: current user)"
}

@test "Default URL not set, docs mention" {
    setup_helper
    run run_configure
    assert_line "checking baseurl... https://example.com/domjudge/"
    assert_line "Warning: base URL is unconfigured; generating team documentation will"
    assert_line "not work out of the box!"
    assert_line "Rerun configure with option '--with-baseurl=BASEURL' to correct this."
    assert_line " * website base URL....: https://example.com/domjudge/"
    assert_line " * documentation.......: /opt/domjudge/doc"
    run run_configure "--with-baseurl=https://contest.example.org"
    assert_line "checking baseurl... https://contest.example.org"
    refute_line "Warning: base URL is unconfigured; generating team documentation will"
    refute_line "not work out of the box!"
    refute_line "Rerun configure with option '--with-baseurl=BASEURL' to correct this."
    assert_line " * website base URL....: https://contest.example.org"
}

@test "Change users" {
    setup_helper
    run run_configure
    assert_line " * default user........: domjudge-bats-user"
    assert_line " * runguard user.......: domjudge-run"
    assert_line " * runguard group......: domjudge-run"
    assert_regex "^ \* webserver group\.\.\.\.\.: (www-data|apache|nginx|www|wwwrun)$"
    run run_configure "--with-domjudge-user=other_user --with-webserver-group=other_group --with-runuser=other-domjudge-run --with-rungroup=other-run-group"
    refute_line " * default user........: domjudge-bats-user"
    refute_line " * runguard user.......: domjudge-run"
    refute_line " * runguard group......: domjudge-run"
    for group in www-data apache nginx; do
        refute_line " * webserver group.....: $group"
    done
    assert_line " * default user........: other_user"
    assert_line " * runguard user.......: other-domjudge-run"
    assert_line " * runguard group......: other-run-group"
    assert_line " * webserver group.....: other_group"
}

@test "No docs" {
    setup_helper
    run run_configure
    assert_line " * documentation.......: /opt/domjudge/doc"
    run run_configure --enable-doc-build
    assert_line " * documentation.......: /opt/domjudge/doc"
    run run_configure --disable-doc-build
    assert_line " * documentation.......: /opt/domjudge/doc (disabled)"
}

@test "/opt configured" {
    setup_helper
    run run_configure
    assert_line " * prefix..............: /opt/domjudge"
    assert_line " * documentation.......: /opt/domjudge/doc"
    assert_line " * domserver...........: /opt/domjudge/domserver"
    assert_line "    - bin..............: /opt/domjudge/domserver/bin"
    assert_line "    - etc..............: /opt/domjudge/domserver/etc"
    assert_line "    - lib..............: /opt/domjudge/domserver/lib"
    assert_line "    - log..............: /opt/domjudge/domserver/log"
    assert_line "    - run..............: /opt/domjudge/domserver/run"
    assert_line "    - sql..............: /opt/domjudge/domserver/sql"
    assert_line "    - tmp..............: /opt/domjudge/domserver/tmp"
    assert_line "    - webapp...........: /opt/domjudge/domserver/webapp"
    assert_line "    - example_problems.: /opt/domjudge/domserver/example_problems"
    assert_line " * judgehost...........: /opt/domjudge/judgehost"
    assert_line "    - bin..............: /opt/domjudge/judgehost/bin"
    assert_line "    - etc..............: /opt/domjudge/judgehost/etc"
    assert_line "    - lib..............: /opt/domjudge/judgehost/lib"
    assert_line "    - libjudge.........: /opt/domjudge/judgehost/lib/judge"
    assert_line "    - log..............: /opt/domjudge/judgehost/log"
    assert_line "    - run..............: /opt/domjudge/judgehost/run"
    assert_line "    - tmp..............: /opt/domjudge/judgehost/tmp"
    assert_line "    - judge............: /opt/domjudge/judgehost/judgings"
    assert_line "    - chroot...........: /chroot/domjudge"
}

@test "Prefix configured" {
    setup_helper
    run run_configure --prefix=/tmp
    refute_line " * prefix..............: /opt/domjudge"
    refute_line " * documentation.......: /opt/domjudge/doc"
    refute_line " * domserver...........: /opt/domjudge/domserver"
    refute_line "    - bin..............: /opt/domjudge/domserver/bin"
    refute_line "    - tmp..............: /opt/domjudge/domserver/tmp"
    refute_line "    - example_problems.: /opt/domjudge/domserver/example_problems"
    refute_line " * judgehost...........: /opt/domjudge/judgehost"
    refute_line "    - libjudge.........: /opt/domjudge/judgehost/lib/judge"
    refute_line "    - log..............: /opt/domjudge/judgehost/log"
    refute_line "    - run..............: /opt/domjudge/judgehost/run"
    refute_line "    - tmp..............: /opt/domjudge/judgehost/tmp"
    refute_line "    - judge............: /opt/domjudge/judgehost/judgings"
    assert_line " * prefix..............: /tmp"
    assert_line " * documentation.......: /tmp/doc"
    assert_line " * domserver...........: /tmp/domserver"
    assert_line " * judgehost...........: /tmp/judgehost"
    assert_line "    - judge............: /tmp/judgehost/judgings"
}

@test "Check FHS" {
    setup_helper
    run run_configure --enable-fhs
    refute_line " * prefix..............: /opt/domjudge"
    refute_line " * documentation.......: /opt/domjudge/doc"
    refute_line " * domserver...........: /opt/domjudge/domserver"
    refute_line "    - webapp...........: /opt/domjudge/domserver/webapp"
    refute_line "    - example_problems.: /opt/domjudge/domserver/example_problems"
    refute_line " * judgehost...........: /opt/domjudge/judgehost"
    refute_line "    - lib..............: /opt/domjudge/judgehost/lib"

    assert_line " * prefix..............: /usr/local"
    assert_line " * documentation.......: /usr/local/share/doc/domjudge"
    assert_line " * domserver...........: "
    assert_line "    - bin..............: /usr/local/bin"
    assert_line "    - etc..............: /usr/local/etc/domjudge"
    assert_line "    - lib..............: /usr/local/lib/domjudge"
    assert_line "    - log..............: /usr/local/var/log/domjudge"
    assert_line "    - run..............: /usr/local/var/run/domjudge"
    assert_line "    - sql..............: /usr/local/share/domjudge/sql"
    assert_line "    - tmp..............: /tmp"
    assert_line "    - webapp...........: /usr/local/share/domjudge/webapp"
    assert_line "    - example_problems.: /usr/local/share/domjudge/example_problems"
    assert_line " * judgehost...........: "
    assert_line "    - bin..............: /usr/local/bin"
    assert_line "    - etc..............: /usr/local/etc/domjudge"
    assert_line "    - lib..............: /usr/local/lib/domjudge"
    assert_line "    - libjudge.........: /usr/local/lib/domjudge/judge"
    assert_line "    - log..............: /usr/local/var/log/domjudge"
    assert_line "    - run..............: /usr/local/var/run/domjudge"
    assert_line "    - tmp..............: /tmp"
    assert_line "    - judge............: /usr/local/var/lib/domjudge/judgings"
    assert_line "    - chroot...........: /chroot/domjudge"
}

@test "Alternative dirs together with FHS" {
    setup_helper
    run run_configure --enable-fhs --with-domserver_webappdir=/run/webapp --with-domserver_tmpdir=/tmp/domserver --with-judgehost_tmpdir=/srv/tmp --with-judgehost_judgedir=/srv/judgings --with-judgehost_chrootdir=/srv/chroot/domjudge
    assert_line " * prefix..............: /usr/local"
    assert_line " * documentation.......: /usr/local/share/doc/domjudge"
    assert_line " * domserver...........: "
    assert_line "    - bin..............: /usr/local/bin"
    assert_line "    - etc..............: /usr/local/etc/domjudge"
    assert_line "    - lib..............: /usr/local/lib/domjudge"
    assert_line "    - log..............: /usr/local/var/log/domjudge"
    assert_line "    - run..............: /usr/local/var/run/domjudge"
    assert_line "    - sql..............: /usr/local/share/domjudge/sql"
    refute_line "    - tmp..............: /tmp"
    assert_line "    - tmp..............: /tmp/domserver"
    refute_line "    - webapp...........: /usr/local/share/domjudge/webapp"
    assert_line "    - webapp...........: /run/webapp"
    assert_line "    - example_problems.: /usr/local/share/domjudge/example_problems"
    assert_line " * judgehost...........: "
    assert_line "    - bin..............: /usr/local/bin"
    assert_line "    - etc..............: /usr/local/etc/domjudge"
    assert_line "    - lib..............: /usr/local/lib/domjudge"
    assert_line "    - libjudge.........: /usr/local/lib/domjudge/judge"
    assert_line "    - log..............: /usr/local/var/log/domjudge"
    assert_line "    - run..............: /usr/local/var/run/domjudge"
    refute_line "    - tmp..............: /tmp"
    assert_line "    - tmp..............: /srv/tmp"
    refute_line "    - judge............: /usr/local/var/lib/domjudge/judgings"
    assert_line "    - judge............: /srv/judgings"
    refute_line "    - chroot...........: /chroot/domjudge"
    assert_line "    - chroot...........: /srv/chroot/domjudge"
}

@test "Alternative dirs together with defaults" {
    setup_helper
    run run_configure "--with-judgehost_tmpdir=/srv/tmp --with-judgehost_judgedir=/srv/judgings --with-judgehost_chrootdir=/srv/chroot --with-domserver_logdir=/log"
    assert_line " * prefix..............: /opt/domjudge"
    assert_line " * documentation.......: /opt/domjudge/doc"
    assert_line " * domserver...........: /opt/domjudge/domserver"
    refute_line "    - log..............: /opt/domjudge/domserver/log"
    assert_line "    - log..............: /log"
    assert_line " * judgehost...........: /opt/domjudge/judgehost"
    refute_line "    - tmp..............: /opt/domjudge/judgehost/tmp"
    assert_line "    - tmp..............: /srv/tmp"
    refute_line "    - judge............: /opt/domjudge/judgehost/judgings"
    assert_line "    - judge............: /srv/judgings"
    refute_line "    - chroot...........: /chroot/domjudge"
    assert_line "    - chroot...........: /srv/chroot"
}

@test "Build default [root/root] (effective host does both domserver & judgehost)" {
    build_default root root
}

# Cleanup for next runs
rm -rf /opt/domjudge >/dev/zero
make clean >/dev/zero

@test "Build default [user/root] (effective host does both domserver & judgehost)" {
    build_default "$u" root
}

# Cleanup for next runs
rm -rf /opt/domjudge &>/dev/zero
make clean &>/dev/zero

@test "Build default [user/user] (effective host does both domserver & judgehost)" {
    build_default "$u" "$u" /tmp/domjudge
}

# Cleanup for next runs
rm -rf /opt/domjudge &>/dev/zero
make clean &>/dev/zero

@test "Build default (effective host does everything)" {
    build_default root root "" build
}

# Cleanup for next runs
rm -rf /opt/domjudge &>/dev/zero
make clean &>/dev/zero

@test "Build domserver disabled" {
    if [ "$distro_id" = "ID=fedora" ]; then
        # Fails as libraries are not found
        skip
    fi
    setup_helper
    run run_configure --disable-domserver-build
    refute_line " * domserver...........: /opt/domjudge/domserver"
    for group in www-data apache nginx; do
        refute_line " * webserver group.....: $group"
    done
    assert_line " * judgehost...........: /opt/domjudge/judgehost"
    assert_line " * runguard group......: domjudge-run"
    run make domserver
    assert_failure
    run make judgehost
    assert_success
    run make install-judgehost
    assert_success
    run ls /opt/domjudge/judgehost/judgings
    assert_success
}

# Cleanup for next runs
rm -rf /opt/domjudge &>/dev/zero
make clean &>/dev/zero

@test "Build judgehost disabled [user/root]" {
    setup_helper
    run run_configure --disable-judgehost-build --prefix=/tmp/domjudge
    assert_line " * domserver...........: /tmp/domjudge/domserver"
    assert_regex "^ \* webserver group\.\.\.\.\.: (www-data|apache|nginx)$"
    refute_line " * judgehost...........: /tmp/domjudge/judgehost"
    refute_line " * runguard group......: domjudge-run"
    run su $u -c "make domserver 2>&1"
    assert_success
    run make install-domserver
    assert_success
    run ls /tmp/domjudge/domserver/webapp
    assert_success
    run su $u -c "make judgehost"
    assert_failure
}

# Cleanup for next runs
rm -rf /tmp/domjudge &>/dev/zero
make clean &>/dev/zero

@test "Documentation generation works" {
    docs_required_install
    setup_helper
    run run_configure
    check_docs
    make clean
    run run_configure --with-baseurl=http://domjudge/alias
    check_docs
}

# Cleanup for next runs
make clean &>/dev/zero

@test "Build all (effective host does everything, also docs)" {
    build_default root root "" all
}

# Cleanup for next runs
rm -rf /opt/domjudge &>/dev/zero
make clean &>/dev/zero

@test "Running make clean shows no permission errors" {
    docs_required_install
    setup_helper
    run run_configure
    assert_line " * domserver...........: /opt/domjudge/domserver"
    assert_regex "^ \* webserver group\.\.\.\.\.: (www-data|apache|nginx)$"
    assert_line " * judgehost...........: /opt/domjudge/judgehost"
    run make all
    if [ "$distro_id" = "ID=fedora" ]; then
        # Because compiling fails
        assert_failure
    else
        assert_success
    fi
    run make install-domserver
    assert_success
    run make install-judgehost
    if [ "$distro_id" = "ID=fedora" ]; then
        # Because compiling fails
        assert_failure
    else
        assert_success
    fi
    run make install-docs
    assert_success
    run make clean
    # Currently there is still an error
    assert_failure
}
