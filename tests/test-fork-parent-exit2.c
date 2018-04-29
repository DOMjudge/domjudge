/*
 * This code tries to fork a number of child processes and then exit
 * itself after sleeping for a couple of seconds. Runguard should kill all
 * child processes on normal exit, when using Linux cgroups.
 *
 * As the childs are doing some work, this submission should have a
 * CPU time in the range of seconds if we're accounting properly for
 * forked grandchildren. The expected result will depend on whether
 * the program is bound to a single CPU core or not:
 * - If bound to a single CPU core, the cpu time should be almost
 *   equal to the wall clock time, giving WRONG-ANSWER.
 * - If not bound to a single core, the cpu time should be the factor
 *   of the number of cores higher than wall clock time, and probably
 *   give a TIMELIMIT.
 *
 * @EXPECTED_RESULTS@: CHECK-MANUALLY
 */

#include <unistd.h>
#include <stdio.h>

int array[2000][2000];

int main()
{
	int pid, i, j, k, successes = 0, failures = 0;
	for(i = 0; i < 100; i++) {
		int pid = fork();
		if (pid == -1) {
			failures += 1;
		}
		else if (pid == 0) {
			for(j = 0; j < 2000; j++) {
				for(k = 0; k < 2000; k++) {
					array[j][k] += array[k][j] * (i+42);
				}
			}
			printf("%d\n", array[42][42]);
		}
		else {
			successes += 1;
		}
	}
	sleep(3);
	printf("%d forks succeeded, %d failed\n", successes, failures);
	printf("parent process exiting now\n");

	return 0;
}
