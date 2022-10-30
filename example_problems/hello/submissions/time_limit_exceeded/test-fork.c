/*
 * This code tries to fork as many processes as possible. The limit on
 * the number of processes will limit that and the program will
 * timeout.
 *
 * The result should be a TIMELIMIT and the running forked programs
 * killed by testcase_run.
 *
 * @EXPECTED_RESULTS@: TIMELIMIT
 */

#include <unistd.h>

int main()
{
	int parent = 1;
	int a = 0;

	while ( parent ) {
		if ( fork()==0 ) {
			parent = 0;
			malloc(1);
		}
	}

	while ( 1 ) a++;

	return 0;
}
