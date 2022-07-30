#!/usr/bin/env bats

load 'assert'

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

@test "Test chroot works with architecture: $ARCH" {
    if [ -z ${ARCH+x} ]; then
        skip "Arch not set"
    fi
    run ./dj_make_chroot -a $ARCH
    assert_success
    assert_partial "Done building chroot in /builds/DOMjudge/domjudge/chroot/domjudge"
    run ./dj_run_chroot "dpkg --print-architecture"
    assert_success
    assert_partial "$ARCH"
}

@test "Test chroot works without architecture given" {
    if [ -n ${ARCH+x} ]; then
        skip "Arch set"
    fi
    HOSTARCH=$(dpkg --print-architecture)
    run ./dj_make_chroot
    assert_success
    assert_line "Done building chroot in /builds/DOMjudge/domjudge/chroot/domjudge"
    run ./dj_run_chroot
    assert_success
    CHROOTARCH=$(dpkg --print-architecture)
    assert_equal "$CHROOTARCH" "$HOST$ARCH" 
}

@test "Test chroot fails if unsupported architecture given" {
    if [ -n ${ARCH+x} ]; then
        skip "Arch set"
    fi
    run ./dj_make_chroot -a dom04
    assert_failure
    assert_line "Error: Architecture dom04 not supported for Ubuntu"
}

@test "Passing the Distro gives a chroot of that Distro" {
    if [ -n ${DISTRO+x} ]; then
        skip "Distro not set"
    fi
    run ./dj_make_chroot -D $DISTRO
    assert_success
    assert_line "Done building chroot in /builds/DOMjudge/domjudge/chroot/domjudge"
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
    if [ -z ${DISTRO+x} ]; then
        skip "Distro set"
    fi
    run ./dj_make_chroot -D "BSD"
    assert_failure
    assert_line "Error: Invalid distribution specified, only 'Debian' and 'Ubuntu' are supported."
}

@test "Unknown Release breaks" {
    if [ -z ${RELEASE+x} ]; then
        skip "Release set"
    fi
    run ./dj_make_chroot -R "Olympos"
    assert_failure
    assert_line "E: No such script: /usr/share/debootstrap/scripts/Olympus"
}

#@test "Passing Debian Release 


#@test "contest via parameter overrides environment" {
#    run ./submit -c bestaatniet
#    assert_failure 1
#    assert_partial "error: No (valid) contest specified"
#
#    run ./submit --contest=bestaatookniet
#    assert_failure 1
#    assert_partial "error: No (valid) contest specified"
#}
#
#@test "hello problem id and name are in help output" {
#    run ./submit --help
#    assert_success
#    assert_regex "hello *- *Hello World"
#}
#
#@test "languages and extensions are in help output" {
#    run ./submit --help
#    assert_success
#    assert_regex "C *- *c"
#    assert_regex "C\+\+ *- *c\+\+, cc, cpp, cxx"
#    assert_regex "Java *- *java"
#}
#
#@test "stale file emits warning" {
#    touch -d '2000-01-01' $BATS_TMPDIR/test-hello.c
#    run ./submit -p hello $BATS_TMPDIR/test-hello.c <<< "n"
#    assert_regex "test-hello.c' has not been modified for [0-9]* minutes!"
#}
#
#@test "recent file omits warning" {
#    touch $BATS_TMPDIR/test-hello.c
#    run ./submit -p hello $BATS_TMPDIR/test-hello.c <<< "n"
#    refute_line -e "test-hello.c' has not been modified for [0-9]* minutes!"
#}
#
#@test "binary file emits warning" {
#    cp $(which bash) $BATS_TMPDIR/binary.c
#    run ./submit -p hello $BATS_TMPDIR/binary.c <<< "n"
#    assert_partial "binary.c' is detected as binary/data!"
#}
#
#@test "empty file emits warning" {
#    touch $BATS_TMPDIR/empty.c
#    run ./submit -p hello $BATS_TMPDIR/empty.c <<< "n"
#    assert_partial "empty.c' is empty"
#}
#
#@test "detect problem name and language" {
#    cp ../tests/test-hello.java $BATS_TMPDIR/hello.java
#    run ./submit $BATS_TMPDIR/hello.java <<< "n"
#    assert_line "Submission information:"
#    assert_line "  problem:     hello"
#    assert_line "  language:    Java"
#}
#
#@test "options override detection of problem name and language" {
#    cp ../tests/test-hello.java $BATS_TMPDIR/hello.java
#    run ./submit -p boolfind -l cpp $BATS_TMPDIR/hello.java <<< "n"
#    assert_line "Submission information:"
#    assert_line "  problem:     boolfind"
#    assert_line "  language:    C++"
#}
#
#@test "non existing problem name emits error" {
#    cp ../tests/test-hello.java $BATS_TMPDIR/hello.java
#    run ./submit -p nonexistent -l cpp $BATS_TMPDIR/hello.java <<< "n"
#    assert_failure 1
#    assert_partial "error: No known problem specified or detected"
#}
#
#@test "non existing language name emits error" {
#    cp ../tests/test-hello.java $BATS_TMPDIR/hello.java
#    run ./submit -p boolfind -l nonexistent $BATS_TMPDIR/hello.java <<< "n"
#    assert_failure 1
#    assert_partial "error: No known language specified or detected"
#}
#
#@test "detect entry point Java" {
#    skip "Java does not require an entry point in the default installation"
#    run ./submit -p hello ../tests/test-hello.java <<< "n"
#    assert_line '  entry point: test-hello'
#}
#
#@test "detect entry point Python" {
#    skip "Python does not require an entry point in the default installation"
#    touch $BATS_TMPDIR/test-extra.py
#    run ./submit -p hello ../tests/test-hello.py $BATS_TMPDIR/test-extra.py <<< "n"
#    assert_line '  entry point: test-hello.py'
#}
#
#@test "detect entry point Kotlin" {
#    run ./submit --help
#    if ! echo "$output" | grep 'Kotlin:' ; then
#        skip "Kotlin not enabled"
#    fi
#    run ./submit -p hello ../tests/test-hello.kt <<< "n"
#    assert_line '  entry point: Test_helloKt'
#}
#
#@test "options override entry point" {
#    run ./submit -p hello -e Main ../tests/test-hello.java <<< "n"
#    assert_line '  entry point: Main'
#
#    run ./submit -p hello --entry_point=mypackage.Main ../tests/test-hello.java <<< "n"
#    assert_line '  entry point: mypackage.Main'
#}
#
#@test "accept multiple files" {
#    cp ../tests/test-hello.java ../tests/test-classname.java ../tests/test-package.java $BATS_TMPDIR/
#    run ./submit -p hello $BATS_TMPDIR/test-*.java <<< "n"
#    assert_line "  filenames:   $BATS_TMPDIR/test-classname.java $BATS_TMPDIR/test-hello.java $BATS_TMPDIR/test-package.java"
#}
#
#@test "deduplicate multiple files" {
#    cp ../tests/test-hello.java ../tests/test-package.java $BATS_TMPDIR/
#    run ./submit -p hello $BATS_TMPDIR/test-hello.java $BATS_TMPDIR/test-hello.java $BATS_TMPDIR/test-package.java <<< "n"
#    assert_line "  filenames:   $BATS_TMPDIR/test-hello.java $BATS_TMPDIR/test-package.java"
#}
#
#@test "submit solution" {
#    run ./submit -y -p hello ../tests/test-hello.c
#    assert_success
#    assert_regex "Submission received: id = s[0-9]*, time = [0-9]{2}:[0-9]{2}:[0-9]{2}"
#    assert_regex "Check http[^ ]*/[0-9]* for the result."
#}
##!/usr/bin/env bats
## These tests can be run without a working DOMjudge API endpoint.
#
#load 'assert'
#
#setup() {
#    export SUBMITBASEHOST="domjudge.example.org"
#    export SUBMITBASEURL="https://${SUBMITBASEHOST}/somejudge"
#}
#
#
#@test "baseurl set in environment" {
#    run ./submit
#    assert_failure 1
#    assert_regex "$SUBMITBASEHOST.*/api(/.*)?/contests.*: \[Errno -2\] Name or service not known"
#}
#
#@test "baseurl via parameter overrides environment" {
#    run ./submit --url https://domjudge.example.edu
#    assert_failure 1
#    assert_regex "domjudge.example.edu.*/api(/.*)?/contests.*: \[Errno -2\] Name or service not known"
#
#    run ./submit -u https://domjudge3.example.edu
#    assert_failure 1
#    assert_regex "domjudge3.example.edu.*/api(/.*)?/contests.*: \[Errno -2\] Name or service not known"
#}
#
#@test "baseurl can end in slash" {
#    run ./submit --url https://domjudge.example.edu/domjudge/
#    assert_failure 1
#    assert_regex "domjudge.example.edu.*/api(/.*)?/contests.*: \[Errno -2\] Name or service not known"
#}
#
#@test "display basic usage information" {
#    run ./submit --help
#    assert_success
#    assert_line "usage: submit [--version] [-h] [-c CONTEST] [-p PROBLEM] [-l LANGUAGE] [-e ENTRY_POINT]"
#    assert_line "              [-v [{DEBUG,INFO,WARNING,ERROR,CRITICAL}]] [-q] [-y] [-u URL]"
#    # The help printer does print this differently on versions of argparse for nargs=*.
#    assert_regex "              (filename )?[filename ...]"
#    assert_line "Submit a solution for a problem."
#    assert_success
#    assert_line "The (pre)configured URL is '$SUBMITBASEURL/'"
#    assert_success
#    assert_regex "~/\\.netrc"
#    assert_failure 2
#    assert_line "submit: error: unrecognized arguments: --doesnotexist"
#    assert_failure 1
#    assert_partial "set verbosity to INFO"
