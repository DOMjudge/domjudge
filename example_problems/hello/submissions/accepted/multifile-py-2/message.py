'''
This is an auxiliary source file that declares another public class 'message'.
'''

class Message:
    msg = ""

    def __init__(self):
        self.msg = "Hello world!"

    def myPrint(self):
        print(self.msg)
