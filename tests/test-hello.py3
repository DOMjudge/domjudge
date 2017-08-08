# This should give CORRECT on the default problem 'hello'.
#
# @EXPECTED_RESULTS@: CORRECT

import sys,os;

if os.getenv('DOMJUDGE', 'none') != 'none':
    print("Hello world!")
else:
    print("Environment variable DOMJUDGE not defined.")
    sys.exit(1)
