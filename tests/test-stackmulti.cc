/*
 * This starts multiple threads. Each thread allocates a stack where
 * pthread_create() defaults to use the soft stack size limit.
 * This should work, but previously gave memory limit errors when
 * cgroups were disabled, because the soft stack size was set to
 * the memory limit.
 *
 * @EXPECTED_RESULTS@: CORRECT
 */

#include <iostream>
#include <thread>

using namespace std;

const int nthreads = 4;

void start(int i)
{
	if ( i==nthreads-1 ) {
		printf("Hello world!\n");
	}
}

int main()
{
	thread t[nthreads];

	for(int i=0; i<nthreads; i++) t[i] = thread(start,i);
	for(int i=0; i<nthreads; i++) t[i].join();

	return 0;
}
