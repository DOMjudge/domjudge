#!/usr/bin/env bats

load 'assert'

source .github/jobs/configure-checks/functions.sh

@test "Install GNU C only" {
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
