/*
 * This code tries to send a SIGSTOP (and later SIGCONT) to the parent
 * controlling process. If this succeeds, the controller will be
 * suspended and not terminate the command after the timelimit.
 *
 * This should give TIMELIMIT on the default problem 'hello'.
 *
 * @EXPECTED_RESULTS@: TIMELIMIT
 */

#include <signal.h>
#include <unistd.h>
#include <stdio.h>

int main()
{
	int sleeptime = 10;
	int ppid = getppid();

	kill(ppid, SIGSTOP);

	signal(SIGTERM, SIG_IGN);

	printf("sleeping for %d seconds\n",sleeptime);
	sleep(sleeptime);
	printf("still alive!\n");

	kill(ppid, SIGCONT);

	return 0;
}
