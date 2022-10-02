/*
 * This code tries to fork a number of child processes and then exit
 * itself. Runguard should kill all child processes on normal exit,
 * when using Linux cgroups.
 *
 * The result should be a WRONG-ANSWER and the running forked child
 * processes killed. Without cgroup support, this will crash the
 * judgedaemon because the child processes are found still running.
 *
 * @EXPECTED_RESULTS@: WRONG-ANSWER
 */

#include <unistd.h>
#include <stdio.h>

int main()
{
	int pid, i, successes = 0, failures = 0;
	for(i = 0; i < 100; i++) {
		int pid = fork();
		if (pid == -1) {
			failures += 1;
		}
		else if (pid == 0) {
			while (1) {}; /* Child loops */
		}
		else {
			successes += 1;
		}
	}
	printf("%d forks succeeded, %d failed\n", successes, failures);
	printf("parent process exiting now\n");

	return 0;
}
