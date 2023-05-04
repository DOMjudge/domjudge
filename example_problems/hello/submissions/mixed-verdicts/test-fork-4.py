'''
This code tries to fork as many processes as possible. The limit on
the number of processes will limit that and the program will
timeout.

The result should be a TIMELIMIT and the running forked programs
killed by testcase_run. In pypy3 the ThreadPool is not implemented so
this yields an RUN-ERROR

@EXPECTED_RESULTS@: TIMELIMIT,RUN-ERROR
'''

from multiprocessing import ThreadPool

def thread_function(name):
	a = 0
	while True:
		a += 1
		a = a%10

# Directly make all processes
numb_processes = 1000*60*10
with ThreadPool(numb_processes) as p:
	p.map(thread_function, range(numb_processes))
