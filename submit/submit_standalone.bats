#!/usr/bin/env bats

# These tests can be run without a working DOMjudge api endpoint.

@test "version output" {
    run ./submit --version
    [ "$status" -eq 0 ]
    echo "${lines[0]}" | grep "^submit -- part of DOMjudge"
    [ "${lines[1]}" = "Written by the DOMjudge developers" ]
}

setup() {
    export SUBMITBASEURL="https://domjudge.example.org/somejudge"
}

@test "baseurl set in environment" {
    run ./submit
    echo $output | grep -E "warning: '$SUBMITBASEURL/api(/.*)?/contests': Could not resolve host"
    [ "$status" -eq 1 ]
}

@test "baseurl via parameter overrides environment" {
    run ./submit --url https://domjudge.example.edu
    echo $output | grep -E "warning: 'https://domjudge.example.edu/api(/.*)?/contests': Could not resolve host"
    run ./submit -u https://domjudge3.example.edu
    echo $output | grep -E "warning: 'https://domjudge3.example.edu/api(/.*)?/contests': Could not resolve host"
    [ "$status" -eq 1 ]
}

@test "baseurl can end in slash" {
    run ./submit --url https://domjudge.example.edu/domjudge/
    echo $output | grep -E "warning: 'https://domjudge.example.edu/domjudge/api(/.*)?/contests': Could not resolve host"
    [ "$status" -eq 1 ]
}

@test "display basic usage information" {
    run ./submit --help
    [ "${lines[3]}" = "Usage: ./submit [OPTION]... FILENAME..." ]
    [ "${lines[4]}" = "Submit a solution for a problem." ]
    [ "$status" -eq 0 ]
}

@test "usage information displays API url" {
    run ./submit --help
    echo $output | grep "The (pre)configured URL is '$SUBMITBASEURL/'."
}

@test "nonexistent option refers to help" {
    run ./submit --doesnotexist
    [ "${lines[1]}" = "Type './submit --help' to get help." ]
    [ "$status" -eq 1 ]
}

@test "verbosity option defaults to 6" {
    run ./submit -v
    echo $output | grep "set verbosity to 6"
}
