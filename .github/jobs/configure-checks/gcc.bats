#!/usr/bin/env bats

load 'assert'

source .github/jobs/configure-checks/functions.sh

@test "Install GNU C only" {
    repo-remove clang g++
    repo-install gcc libcgroup-dev
    compiler_c_only_assertions
}
