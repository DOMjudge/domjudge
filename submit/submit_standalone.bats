#!/usr/bin/env bats
# These tests can be run without a working DOMjudge API endpoint.

load 'assert'

setup() {
    export SUBMITBASEHOST="domjudge.example.org"
    export SUBMITBASEURL="https://${SUBMITBASEHOST}/somejudge"
}

@test "version output" {
    run ./submit --version
    assert_success
    assert_regex "^submit -- part of DOMjudge"
    assert_line "Written by the DOMjudge developers"
}

@test "baseurl set in environment" {
    run ./submit
    assert_failure 1
    assert_regex "$SUBMITBASEHOST"
}

@test "baseurl via parameter overrides environment" {
    run ./submit --url https://domjudge.example.edu
    assert_failure 1
    assert_regex "domjudge\.example\.edu"
}

@test "display basic usage information" {
    run ./submit --help
    assert_success
    assert_line "usage: submit [--version] [-h] [-c CONTEST] [-P] [-p PROBLEM] [-l LANGUAGE] [-e ENTRY_POINT]"
    assert_line "              [-v [{DEBUG,INFO,WARNING,ERROR,CRITICAL}]] [-q] [-y] [-u URL]"
    # The help printer does print this differently on versions of argparse for nargs=*.
    assert_regex "              (filename )?[filename ...]"
    assert_line "Submit a solution for a problem."
}

@test "usage information displays API url" {
    run ./submit --help
    assert_success
    assert_line "The (pre)configured URL is '$SUBMITBASEURL/'"
}

@test "netrc is mentioned in usage documentation" {
    run ./submit --help
    assert_success
    assert_regex "~/\\.netrc"
}

@test "nonexistent option shows error" {
    run ./submit --doesnotexist
    assert_failure 2
    # Do not count from the start, but take the last line.
    assert_line "submit: error: unrecognized arguments: --doesnotexist"
}

@test "verbosity option defaults to INFO" {
    run ./submit -v
    assert_failure 1
    assert_partial "set verbosity to INFO"
}
