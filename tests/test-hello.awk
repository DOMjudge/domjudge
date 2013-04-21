# This should give CORRECT on the default problem 'hello'.
#
# @EXPECTED_RESULTS@: CORRECT

BEGIN { if ( DOMJUDGE ) print "Hello world!"; else print "variable DOMJUDGE not set" }
