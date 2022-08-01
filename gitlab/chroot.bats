#!/usr/bin/env bats

load 'assert'

CHROOT="/chroot/domjudge"
if [ -n "${CI_JOB_ID+x}" ]; then
    CHROOT="/builds/DOMjudge/domjudge${CHROOT}"
fi
# Cleanup old dir
rm -rf $CHROOT

COMMANDARGS=""
if [ -n "${ARCH+x}" ]; then
    COMMANDARGS="-a $ARCH $COMMANDARGS"
fi

@test "help output" {
    run ./dj_make_chroot -h
    assert_success
    assert_partial "Usage:"
    assert_partial "Creates a chroot environment with Java JRE support using the"
    assert_partial "Debian or Ubuntu GNU/Linux distribution."
    assert_partial "Options"
    assert_partial "Available architectures:"
    assert_partial "Environment Overrides:"
    assert_partial "This script must be run as root"
}

@test "Test chroot fails if unsupported architecture given" {
    if [ -n "${ARCH+x}" ]; then
        skip "Already an Arch set in the commands."
    fi
    for unsupported_arch in 'dom04' 'arm' '64' 'namd64' 'amd64v2'; do
        run ./dj_make_chroot $COMMANDARGS -a $unsupported_arch
        assert_failure
        assert_partial "Error: Architecture $unsupported_arch not supported for"
    done
}

# Creation of the chroot is slow so we run all tests inside 1 large test to speedup.
@test "Test chroot works with args: $COMMANDARGS" {
    run ./dj_make_chroot $COMMANDARGS
    assert_partial "Done building chroot in $CHROOT"
    assert_success
    if [ -n "${ARCH+x}" ]; then
        run ./dj_run_chroot "dpkg --print-architecture"
        assert_partial "$ARCH"
        assert_success
    else
        HOSTARCH=$(dpkg --print-architecture)
        run ./dj_run_chroot "dpkg --print-architecture"
        assert_partial "$HOSTARCH"
        assert_success
    fi
}
