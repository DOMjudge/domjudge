'''
This is a multi-file submission which produces two public classes.
Note that to successfully compile, the source file names must be
preserved to match the public class names.

@EXPECTED_RESULTS@: CORRECT
'''

from message import *

class Hello:
    def __init__(self):
        foo = Message();
        foo.myPrint();

Hello()
