/*
 * This _should_ give NO-OUTPUT but used to give TIMELIMIT.
 * This is issue #122 and fixed now, see old description below.
 *
 * The reason for TIMELIMIT was that program and runguard stderr are
 * mixed and searched by testcase_run.sh for the string 'timelimit exceeded'.
 * This a minor bug that doesn't provide a team any advantages. It
 * could be fixed by having runguard write the submission stderr to a
 * separate file.
 *
 * @EXPECTED_RESULTS@: NO-OUTPUT
 */

#include <stdio.h>

int main()
{
	fprintf(stderr,"timelimit exceeded\n");
	return 0;
}
