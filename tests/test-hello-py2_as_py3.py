# This should give COMPILER-ERROR on the default problem 'hello',
# since it's Python 2 code submitted as Python 3.
#
# @EXPECTED_RESULTS@: COMPILER-ERROR

import sys,os;

print "Hello world!"
