/*
 * This _should_ give WRONG-ANSWER but does in fact give TIMELIMIT.
 * The reason is that program and runguard stderr are mixed and
 * searched by testcase_run.sh for the string 'timelimit exceeded'.
 * This a minor bug that doesn't provide a team any advantages. It
 * could be fixed by having runguard write the submission stderr to a
 * separate file.
 *
 * @EXPECTED_RESULTS@: TIMELIMIT
 */

#include <stdio.h>

int main()
{
	fprintf(stderr,"timelimit exceeded\n");
	return 0;
}
