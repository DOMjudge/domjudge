/*
 * When cgroups are enabled, this will hit timelimit and then be killed.
 *
 * Without cgroups however (not supported anymore), this will crash the judging
 * daemon: it forks processes and places these in a new session, such that
 * the judgedaemon cannot retrace and kill these. They are left running
 * and should be killed before restarting the judging daemon. The
 * cgroups code can detect this because the processes will belong to the
 * same cgroup.
 *
 * @EXPECTED_RESULTS@: TIMELIMIT
 */

#include <unistd.h>
#include <stdlib.h>

int main()
{
	int parent = 1;
	int a = 0;

	while ( parent ) {
		if ( fork()==0 ) {
			parent = 0;
			malloc(1);
			setsid();
		}
	}

	while ( 1 ) a++;

	return 0;
}
