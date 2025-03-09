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

@test "make clean removes all generated files" {
    docs_required_install &>/dev/zero
    setup_helper
    run run_configure
    assert_success
    run make all
    if [ "$distro_id" = "ID=fedora" ]; then
        # Because compiling fails
        assert_failure
    else
        assert_success
    fi
    after_files=$(find . -type f)
    # Find the new files not in the tarball
    filtered_files=()
    for af in $after_files; do
        if [[ ! "${before_files[*]}" =~ "${af}" ]]; then
            filtered_files+=($af)
            echo "# Lost $af" >&3
        fi
    done
    run make distclean
    assert_success
    after_clean_files=$(find . -type f)
    # Files which were there to begin with should
    # still be there.
    for bf in $before_files; do
        if [[ ! "${after_clean_files[*]}" =~ "${bf}" ]]; then
            run echo "Wrongly removed: $bf, existed in original tarball but was removed after 'distclean'"
            refute_line "Wrongly removed: $bf, existed in original tarball but was removed after 'distclean'"
        fi
    done
    # Files which we now find should have been there in the beginning.
    for af in $after_clean_files; do
        if [[ ! "${before_files[*]}" =~ "${af}" ]]; then
            run echo "Wrongly kept: $af, didn't exist in original tarball but was kept after 'distclean'"
            refute_line "Wrongly kept: $af, didn't exist in original tarball but was kept after 'distclean'"
        fi
    done
}
