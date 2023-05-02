# This should give CORRECT on the default problem 'hello',
# since the random extra file will not be passed.
#
# @EXPECTED_RESULTS@: CORRECT

BEGIN { if ( DOMJUDGE ) print "Hello world!"; else print "variable DOMJUDGE not set" }
