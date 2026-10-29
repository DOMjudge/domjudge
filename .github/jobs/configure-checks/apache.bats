#!/usr/bin/env bats

load 'assert'

source .github/jobs/configure-checks/functions.sh

@test "Check for newly added webserver group (Apache)" {
    repo-remove httpd nginx
    for www_group in nginx apache www-data; do
        userdel ${www_group} || true
        groupdel ${www_group} || true
    done
    repo-install httpd
    run ./configure --with-domjudge-user=$u
    assert_line "checking webserver-group... apache (detected)"
    assert_line " * webserver group.....: apache"
}
