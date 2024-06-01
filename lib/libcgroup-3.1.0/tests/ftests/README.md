## Functional Test Suite for libcgroup

This folder contains the functional test suite for libcgroup.
The functional test suite utilizes lxc containers to guarantee
a non-destructive test environment.

The tests can be invoked individually, as a group of related
tests, or from automake via the standard 'make check'
command.

## Invocation

Run a single test (first cd to tests/ftests):

    ./001-cgget-basic_cgget.py
    or
    ./ftests.py -N 15      # Run test #015

Run a suite of tests (first cd to tests/ftests):

    ./ftests.py -s cgget   # Run all cgget tests

Run all the tests by hand

    ./ftests.py
    # This may be advantageous over running make check
    # because it will try to re-use the same lxc
    # container for all of the tests.  This should
    # provide a significant performance increase

Run the tests from automake

    make check
    # Then examine the *.trs and *.log files for
    # specifics regarding each test result

## Results

The test suite will generate test results upon completion of
the test run.  An example result is below:

```
Test Results:
        Run Date:                     Jun 03 13:41:35
        Passed:                               1  test
        Skipped:                              0 tests
        Failed:                               0 tests
-----------------------------------------------------------------
Timing Results:
        Test                               Time (sec)
        ---------------------------------------------------------
        setup                                    6.95
        001-cgget-basic_cgget.py                 0.07
        teardown                                 0.00
        ---------------------------------------------------------
        Total Run Time                           7.02
```

A log file can also be generated to help in debugging failed
tests.  Run `ftests.py -h` to view the syntax.

To generate a log file called foo.log at a debug level (8) run
the following:

        ./ftests.py -l 8 -L foo.log
