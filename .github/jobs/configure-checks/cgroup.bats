#!/usr/bin/env bats

load 'assert'

source .github/jobs/configure-checks/functions.sh

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
