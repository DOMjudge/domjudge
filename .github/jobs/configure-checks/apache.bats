#!/usr/bin/env bats

load 'assert'

source .github/jobs/configure-checks/functions.sh

@test "Check for newly added webserver group (Apache)" {
    package="apache"
    if [ "$distro_id" != "ID=fedora" ]; then
        # Debian/Ubuntu start with a www-data group, we can't
        # remove this group as the post-install script of the packages
        # already assume this.
        # In debian the other groups as nginx & apache are not even created
        package="www-data"
    fi
    repo-remove httpd nginx
    for www_group in nginx apache; do
        userdel ${www_group} || true
        groupdel ${www_group} || true
    done
    repo-install httpd
    run ./configure --with-domjudge-user=$u
    assert_line "checking webserver-group... ${package} (detected)"
    assert_line " * webserver group.....: ${package}"
}
