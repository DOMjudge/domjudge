#!/usr/bin/env bats

load 'assert'

source .github/jobs/configure-checks/functions.sh

@test "Default empty configure" {
    # cleanup from earlier runs
    repo-remove gcc g++ clang
    compiler_c_only_assertions
}
