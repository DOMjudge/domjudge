#!/usr/bin/env bats

load 'assert'

source .github/jobs/configure-checks/functions.sh

@test "Run as root discouraged" {
   setup
   run su root -c "./configure"
   discourage_root="checking domjudge-user... configure: error: installing/running as root is STRONGLY DISCOURAGED, use --with-domjudge-user=root to override."
   assert_line "$discourage_root"
   run su root -c "./configure --with-domjudge-user=root"
   refute_line "$discourage_root"
}

@test "Run as normal user" {
   setup
   run ./configure --with-domjudge-user=$u
   assert_line "checking domjudge-user... $u"
   run su $u -c "./configure"
   assert_line "checking domjudge-user... $u (default: current user)"
}
