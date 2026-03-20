#!/usr/bin/env bats

load 'assert'

source .github/jobs/configure-checks/functions.sh

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
