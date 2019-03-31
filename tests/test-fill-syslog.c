/*
 * Floods syslog and should fail with WRONG-ANSWER.
 * It must be checked manually that syslog is not flooded.
 * This should normally not happen if the runguard works correctly.
 *
 * @EXPECTED_RESULTS@: CHECK-MANUALLY
 */

#include <stdio.h>
#include <syslog.h>

const int maxmesg = 10000;

int main()
{
	int i;

	openlog("domjudge_test-fill-syslog", LOG_PID, LOG_USER);

	for(i=0; i<maxmesg; i++) {
		syslog(LOG_NOTICE,"Fill syslog with nonsense, should not be possible (%06d).",i);
	}

	printf("%d lines written to syslog.\n",maxmesg);

	return 0;
}
