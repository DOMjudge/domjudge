#!/usr/bin/env bats

load 'assert'

source .github/jobs/configure-checks/functions.sh

@test "Default empty configure" {
    # cleanup from earlier runs
    repo-remove gcc g++ clang
    compiler_c_only_assertions
}

@test "Show that 'make maintainer-clean' reverts to the git checkout keeping custom modifications/secrets" {
  expected_custom_files=$(echo ./etc/{dbpasswords,initial_admin_password,restapi,symfony_app}.secret ./webapp/.env.local | tr ' ' '\n')
  files_bare_git=$(find . -type f)
  files_expected_git=$(printf "%s\n%s\n" "$files_bare_git" "$expected_custom_files" | sort)
  run make maintainer-conf
  run make maintainer-install
  files_install=$(find . -type f | sort)
  refute_equal "$files_install" "$files_bare_git"
  refute_equal "$files_install" "$files_expected_git"
  run make maintainer-clean
  files_after=$(find . -type f | sort)
  assert_equal "$files_expected_git" "$files_after"
  # Remove for next tests
  rm -f $expected_custom_files

  run make configure
  assert_success
  run make domserver judgehost docs
  assert_success
  files_install=$(find . -type f | sort)
  refute_equal "$files_bare_git" "$files_install"
  run make maintainer-clean
  assert_success
  files_after=$(find . -type f | sort)
  assert_equal "$files_bare_git" "$files_after"

  run make inplace-conf
  assert_success
  run make inplace-install
  assert_success
  files_install=$(find . -type f | sort)
  refute_equal "$files_bare_git" "$files_install"
  run make maintainer-clean
  files_after=$(find . -type f | sort)
  assert_equal "$files_bare_git" "$files_after"
}

#
#@test "`make distclean` cleans the state to state of `make dist`" {
#  run make dist
#
#  # Cleanup of all configure created files
#  files_before=$(find . -type f | sort)
#  run run_configure
#  files_configure=$(find . -type f | sort)
#  refute_equal "$files_before" "$files_configure"
#  run make distclean
#  files_after=$(find . -type f | sort)
#  assert_equal "$files_before" "$files_after"
#}
#
##
##  # Cleanup of all configure + make targets files
##  files_before=$(find . -type f | sort)
##  run run_configure
##  run make domserver judgehost docs
##  files_make_targets=$(find . -type f | sort)
##  run test "$files_before" = "$files_make_targets"
#  assert_failure
#  run make distclean
#  files_after=$(find . -type f | sort)
#  run test "$files_before" = "$files_after"
#  assert_success
#}
#
#@test "maintainer installation with `make maintainer-clean` cleans the state to the initial state" {
#  files_before=$(find . -type f | sort)
#  run make maintainer-conf maintainer-install
#  files_make=$(find . -type f | sort)
#  run test "$files_before" = "$files_configure"
#  assert_failure
#  run make maintainer-clean
#  files_after=$(find . -type f | sort)
#  run test "$files_before" = "$files_after"
#  assert_success
#}
#
#@test "normal installation with `make maintainer-clean` cleans the state to the initial state" {
#  files_before=$(find . -type f | sort)
#  run make dist
#  run run_configure
#  run make domserver judgehost docs
#  files_make=$(find . -type f | sort)
#  run test "$files_before" = "$files_configure"
#  assert_failure
#  run make maintainer-clean
#  files_after=$(find . -type f | sort)
