#!/usr/bin/env bats

load 'assert'

source .github/jobs/configure-checks/functions.sh

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

@test "Run as root discouraged" {
   setup
   run su root -c "./configure"
   discourage_root="checking domjudge-user... configure: error: installing/running as root is STRONGLY DISCOURAGED, use --with-domjudge-user=root to override."
   assert_line "$discourage_root"
   run su root -c "./configure --with-domjudge-user=root"
   refute_line "$discourage_root"
}
