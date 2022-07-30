#!/usr/bin/env bats

load 'assert'

CHROOT="/chroot/domjudge"
if [ -n "${CI_JOB_ID+x}" ]; then
    CHROOT="/builds/DOMjudge/domjudge${CHROOT}"
fi

@test "help output" {
    # Cleanup old dir if it exists
    rm -rf $CHROOT
    # Start testing
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

@test "Test chroot works with architecture: $ARCH" {
    if [ -z "${ARCH+x}" ]; then
        skip "Arch not set"
    fi
    # Cleanup old dir if it exists
    rm -rf $CHROOT
    # Start testing
    run ./dj_make_chroot -a $ARCH
    assert_success
    assert_partial "Done building chroot in $CHROOT"
    run ./dj_run_chroot "dpkg --print-architecture"
    assert_success
    assert_partial "$ARCH"
}

@test "Test chroot works with no arguments" {
    if [ -n "${ARCH+x}" ] || [ -n "${DISTRO+x}" ] || [ -n "${RELEASE+x}" ]; then
        skip "An argument is set"
    fi
    # Cleanup old dir if it exists
    rm -rf $CHROOT
    # Start testing
    HOSTARCH=$(dpkg --print-architecture)
    run ./dj_make_chroot
    assert_success
    assert_partial "Done building chroot in"
    # $CHROOT"
    #run ./dj_run_chroot
    #assert_success
    #CHROOTARCH=$(dpkg --print-architecture)
    #assert_equal "$CHROOTARCH" "$HOST$ARCH"
}

@test "Test chroot fails if unsupported architecture given" {
    if [ -n "${ARCH+x}" ]; then
        skip "Arch set"
    fi
    # Cleanup old dir if it exists
    rm -rf $CHROOT
    # Start testing
    run ./dj_make_chroot -a dom04
    assert_failure
    if [ -n "${DISTRO+x}" ]; then
        assert_line "Error: Architecture dom04 not supported for $DISTRO"
    else
        assert_line "Error: Architecture dom04 not supported for Ubuntu"
    fi
}

@test "Passing the Distro gives a chroot of that Distro" {
    if [ -z "${DISTRO+x}" ]; then
        skip "Distro not set"
    fi
    # Cleanup old dir if it exists
    rm -rf $CHROOT
    # Start testing
    run ./dj_make_chroot -D $DISTRO
    assert_success
    assert_line "Done building chroot in $CHROOT"
    run ./dj_run_chroot
    run cat /etc/issue
    assert_success
    if [ "Debian" = "$DISTRO" ]; then
        assert_partial "Debian"
    else
        assert_partial "Ubuntu"
    fi
}

@test "Unknown Distro breaks" {
    if [ -n "${DISTRO+x}" ]; then
        skip "Distro set"
    fi
    # Cleanup old dir if it exists
    rm -rf $CHROOT
    # Start testing
    run ./dj_make_chroot -D "BSD"
    assert_failure
    assert_line "Error: Invalid distribution specified, only 'Debian' and 'Ubuntu' are supported."
}

@test "Unknown Release breaks" {
    if [ -n "${DISTRO+x}" ] || [ -n "${RELEASE+x}" ]; then
        skip "Distro/Release set"
    fi
    # Cleanup old dir if it exists
    rm -rf $CHROOT
    # Start testing
    run ./dj_make_chroot -R "Olympos"
    assert_failure
    assert_line "E: No such script: /usr/share/debootstrap/scripts/Olympos"
}

@test "Installing in another chroot dir works" {
    if [ -z "${DIR+x}" ]; then
        skip "Dir not set"
    fi
    # Cleanup old dir if it exists
    rm -rf $CHROOT
    # Start testing
    run ./dj_make_chroot -d $DIR
    assert_success
    assert_partial "Done building chroot in $DIR"
    run cat "$DIR/etc/root-permission-test.txt
    assert_success
    assert_line "This file should not be readable inside the judging environment!"
}

##    run ./submit -c bestaatniet
##    assert_failure 1
##    assert_partial "error: No (valid) contest specified"
##    run ./submit --contest=bestaatookniet
##    assert_failure 1
##    assert_partial "error: No (valid) contest specified"
##    run ./submit --help
##    assert_success
##    assert_regex "hello *- *Hello World"
##    run ./submit --help
##    assert_success
##    assert_regex "C *- *c"
##    assert_regex "C\+\+ *- *c\+\+, cc, cpp, cxx"
##    assert_regex "Java *- *java"
##    touch -d '2000-01-01' $BATS_TMPDIR/test-hello.c
##    run ./submit -p hello $BATS_TMPDIR/test-hello.c <<< "n"
##    assert_regex "test-hello.c' has not been modified for [0-9]* minutes!"
##    touch $BATS_TMPDIR/test-hello.c
##    run ./submit -p hello $BATS_TMPDIR/test-hello.c <<< "n"
##    refute_line -e "test-hello.c' has not been modified for [0-9]* minutes!"
##    cp $(which bash) $BATS_TMPDIR/binary.c
##    run ./submit -p hello $BATS_TMPDIR/binary.c <<< "n"
##    assert_partial "binary.c' is detected as binary/data!"
##    touch $BATS_TMPDIR/empty.c
##    run ./submit -p hello $BATS_TMPDIR/empty.c <<< "n"
##    assert_partial "empty.c' is empty"
##    cp ../tests/test-hello.java $BATS_TMPDIR/hello.java
##    run ./submit $BATS_TMPDIR/hello.java <<< "n"
##    assert_line "Submission information:"
##    assert_line "  problem:     hello"
##    assert_line "  language:    Java"
##    cp ../tests/test-hello.java $BATS_TMPDIR/hello.java
##    run ./submit -p boolfind -l cpp $BATS_TMPDIR/hello.java <<< "n"
##    assert_line "Submission information:"
##    assert_line "  problem:     boolfind"
##    assert_line "  language:    C++"
##    cp ../tests/test-hello.java $BATS_TMPDIR/hello.java
##    run ./submit -p nonexistent -l cpp $BATS_TMPDIR/hello.java <<< "n"
##    assert_failure 1
##    assert_partial "error: No known problem specified or detected"
##    cp ../tests/test-hello.java $BATS_TMPDIR/hello.java
##    run ./submit -p boolfind -l nonexistent $BATS_TMPDIR/hello.java <<< "n"
##    assert_failure 1
##    assert_partial "error: No known language specified or detected"
##    skip "Java does not require an entry point in the default installation"
##    run ./submit -p hello ../tests/test-hello.java <<< "n"
##    assert_line '  entry point: test-hello'
##    skip "Python does not require an entry point in the default installation"
##    touch $BATS_TMPDIR/test-extra.py
##    run ./submit -p hello ../tests/test-hello.py $BATS_TMPDIR/test-extra.py <<< "n"
##    assert_line '  entry point: test-hello.py'
##    run ./submit --help
##    if ! echo "$output" | grep 'Kotlin:' ; then
##        skip "Kotlin not enabled"
##    fi
##    run ./submit -p hello ../tests/test-hello.kt <<< "n"
##    assert_line '  entry point: Test_helloKt'
##    run ./submit -p hello -e Main ../tests/test-hello.java <<< "n"
##    assert_line '  entry point: Main'
##    run ./submit -p hello --entry_point=mypackage.Main ../tests/test-hello.java <<< "n"
##    assert_line '  entry point: mypackage.Main'
##    cp ../tests/test-hello.java ../tests/test-classname.java ../tests/test-package.java $BATS_TMPDIR/
##    run ./submit -p hello $BATS_TMPDIR/test-*.java <<< "n"
##    assert_line "  filenames:   $BATS_TMPDIR/test-classname.java $BATS_TMPDIR/test-hello.java $BATS_TMPDIR/test-package.java"
##    cp ../tests/test-hello.java ../tests/test-package.java $BATS_TMPDIR/
##    run ./submit -p hello $BATS_TMPDIR/test-hello.java $BATS_TMPDIR/test-hello.java $BATS_TMPDIR/test-package.java <<< "n"
##    assert_line "  filenames:   $BATS_TMPDIR/test-hello.java $BATS_TMPDIR/test-package.java"
##    run ./submit -y -p hello ../tests/test-hello.c
##    assert_success
##    assert_regex "Submission received: id = s[0-9]*, time = [0-9]{2}:[0-9]{2}:[0-9]{2}"
##    assert_regex "Check http[^ ]*/[0-9]* for the result."
##
###!/usr/bin/env bats
### These tests can be run without a working DOMjudge API endpoint.
##
##load 'assert'
##
##setup() {
##    export SUBMITBASEHOST="domjudge.example.org"
##    export SUBMITBASEURL="https://${SUBMITBASEHOST}/somejudge"
##}
##
##    run ./submit
##    assert_failure 1
##    assert_regex "$SUBMITBASEHOST.*/api(/.*)?/contests.*: \[Errno -2\] Name or service not known"
##    run ./submit --url https://domjudge.example.edu
##    assert_failure 1
##    assert_regex "domjudge.example.edu.*/api(/.*)?/contests.*: \[Errno -2\] Name or service not known"
##    run ./submit -u https://domjudge3.example.edu
##    assert_failure 1
##    assert_regex "domjudge3.example.edu.*/api(/.*)?/contests.*: \[Errno -2\] Name or service not known"
##    run ./submit --url https://domjudge.example.edu/domjudge/
##    assert_failure 1
##    assert_regex "domjudge.example.edu.*/api(/.*)?/contests.*: \[Errno -2\] Name or service not known"
##    run ./submit --help
##    assert_success
##    assert_line "usage: submit [--version] [-h] [-c CONTEST] [-p PROBLEM] [-l LANGUAGE] [-e ENTRY_POINT]"
##    assert_line "              [-v [{DEBUG,INFO,WARNING,ERROR,CRITICAL}]] [-q] [-y] [-u URL]"
##    # The help printer does print this differently on versions of argparse for nargs=*.
##    assert_regex "              (filename )?[filename ...]"
##    assert_line "Submit a solution for a problem."
##    assert_success
##    assert_line "The (pre)configured URL is '$SUBMITBASEURL/'"
##    assert_success
##    assert_regex "~/\\.netrc"
##    assert_failure 2
##    assert_line "submit: error: unrecognized arguments: --doesnotexist"
##    assert_failure 1
##    assert_partial "set verbosity to INFO"
