/*
 * When cgroups are enabled, this will hit timelimit and then be killed.
 *
 * Without cgroups however, this will crash the judging daemon: it
 * forks processes and places these in a new session, such that
 * testcase_run cannot retrace and kill these. They are left running
 * and should be killed before restarting the judging daemon. The
 * cgroups code can detect this because the processes will belong to the
 * same cgroup.
 *
 * @EXPECTED_RESULTS@: TIMELIMIT
 * (or judgedaemon crash when cgroups disabled)
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
			setsid();
		}
	}

	while ( 1 ) a++;

	return 0;
}
