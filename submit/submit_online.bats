#!/usr/bin/env bats

# These tests assume presence of a running DOMjudge instance at the
# baseurl specified in the `paths.mk` file one directory up.

setup() {
    export SUBMITCONTEST="demo"
    export SUBMITBASEURL="$(grep '^BASEURL' ../paths.mk | tr -s ' ' | cut -d ' ' -f 3)"
}

@test "contest via parameter overrides environment" {
    run ./submit -c bestaatniet
    echo $output | grep "error: no (valid) contest specified"
    run ./submit --contest=bestaatookniet
    echo $output | grep "error: no (valid) contest specified"
    [ "$status" -eq 1 ]
}

@test "hello problem id and name are in help output" {
    run ./submit --help
    echo $output | grep "hello *- *Hello World"
    [ "$status" -eq 0 ]
}

@test "languages and extensions are in help output" {
    run ./submit --help
    echo $output | grep "C: *c"
    echo $output | grep "C++: *c++, cc, cpp, cxx"
    echo $output | grep "Java: *java"
}

@test "stale file emits warning" {
    touch -d '2000-01-01' $BATS_TMPDIR/test-hello.c
    run ./submit -p hello $BATS_TMPDIR/test-hello.c <<< "n"
    echo $output | grep "test-hello.c' has not been modified for [0-9]* minutes!"
}

@test "recent file omits warning" {
    touch $BATS_TMPDIR/test-hello.c
    run ./submit -p hello $BATS_TMPDIR/test-hello.c <<< "n"
    echo $output | grep -v "test-hello.c' has not been modified for [0-9]* minutes!"
}

@test "binary file emits warning" {
    cp $(which bash) $BATS_TMPDIR/binary.c
    run ./submit -p hello $BATS_TMPDIR/binary.c <<< "n"
    echo $output | grep "binary.c' is detected as binary/data!"
}

@test "empty file emits warning" {
    touch $BATS_TMPDIR/empty.c
    run ./submit -p hello $BATS_TMPDIR/empty.c <<< "n"
    echo $output | grep "empty.c' is empty"
}

@test "detect problem name and language" {
    cp ../tests/test-hello.java $BATS_TMPDIR/hello.java
    run ./submit $BATS_TMPDIR/hello.java <<< "n"
    [ "${lines[0]}" = "Submission information:" ]
    [ "${lines[3]}" = "  problem:     hello" ]
    [ "${lines[4]}" = "  language:    Java" ]
}

@test "options override detection of problem name and language" {
    cp ../tests/test-hello.java $BATS_TMPDIR/hello.java
    run ./submit -p boolfind -l cpp $BATS_TMPDIR/hello.java <<< "n"
    [ "${lines[0]}" = "Submission information:" ]
    [ "${lines[3]}" = "  problem:     boolfind" ]
    [ "${lines[4]}" = "  language:    C++" ]
}

@test "non existing problem name emits erorr" {
    cp ../tests/test-hello.java $BATS_TMPDIR/hello.java
    run ./submit -p nonexistent -l cpp $BATS_TMPDIR/hello.java <<< "n"
    echo $output | grep "error: no known problem specified or detected"
    [ "$status" -eq 1 ]
}

@test "non existing language name emits erorr" {
    cp ../tests/test-hello.java $BATS_TMPDIR/hello.java
    run ./submit -p boolfind -l nonexistent $BATS_TMPDIR/hello.java <<< "n"
    echo $output | grep "error: no known language specified or detected"
    [ "$status" -eq 1 ]
}

@test "detect entry point Java" {
    skip "Java does not require an entry point in the default installation"
    run ./submit -p hello ../tests/test-hello.java <<< "n"
    echo "$output" | grep '  entry point: test-hello'
}

@test "detect entry point Python" {
    skip "Python does not require an entry point in the default installation"
    touch $BATS_TMPDIR/test-extra.py
    run ./submit -p hello ../tests/test-hello.py $BATS_TMPDIR/test-extra.py <<< "n"
    echo "$output" | grep '  entry point: test-hello.py'
}

@test "detect entry point Kotlin" {
    run ./submit --help
    if ! echo "$output" | grep 'Kotlin:' ; then
        skip "Kotlin not enabled"
    fi
    run ./submit -p hello ../tests/test-hello.kt <<< "n"
    echo "$output" | grep '  entry point: Test_helloKt'
}

@test "options override entry point" {
    run ./submit -p hello -e Main ../tests/test-hello.java <<< "n"
    echo "$output" | grep '  entry point: Main'
    run ./submit -p hello --entry_point=mypackage.Main ../tests/test-hello.java <<< "n"
    echo "$output" | grep '  entry point: mypackage.Main'
}

@test "accept multiple files" {
    cp ../tests/test-hello.java ../tests/test-classname.java ../tests/test-package.java $BATS_TMPDIR/
    run ./submit -p hello $BATS_TMPDIR/test-*.java <<< "n"
    [ "${lines[1]}" = "  filenames:   $BATS_TMPDIR/test-classname.java $BATS_TMPDIR/test-hello.java $BATS_TMPDIR/test-package.java" ]
}

@test "deduplicate multiple files" {
    cp ../tests/test-hello.java ../tests/test-package.java $BATS_TMPDIR/
    run ./submit -p hello $BATS_TMPDIR/test-hello.java $BATS_TMPDIR/test-hello.java $BATS_TMPDIR/test-package.java <<< "n"
    [ "${lines[1]}" = "  filenames:   $BATS_TMPDIR/test-hello.java $BATS_TMPDIR/test-package.java" ]
}

@test "submit solution" {
    run ./submit -y -p hello ../tests/test-hello.c
    echo $output | grep "Submission received, id = s[0-9]*"
}
