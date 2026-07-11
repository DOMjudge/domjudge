#!/usr/bin/env bats

load 'assert'

source .github/jobs/configure-checks/functions.sh

@test "Install GNU C++ only" {
    # This does work due to dependencies
    repo-remove clang gcc
    repo-install g++ libcgroup-dev
    compiler_assertions gcc g++
    assert_line "checking for gcc... gcc"
    assert_line "checking for g++... g++"
    compile_assertions_finished
}
