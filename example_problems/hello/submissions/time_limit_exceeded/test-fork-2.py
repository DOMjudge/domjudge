'''
This code tries to fork as many processes as possible. The limit on
the number of processes will limit that and the program will
timeout.

The result should be a TIMELIMIT and the running forked programs
killed by testcase_run.

@EXPECTED_RESULTS@: TIMELIMIT
'''

from multiprocessing import Process

def thread_function(name):
	a = 0
	while True:
		a += 1
		a = a%10

while True:
    p = Process(target=thread_function, args=('foo',))
    p.start()
