/* This tries to write to a file in the current directory and read
 * from it again. There is no clear specification on what should
 * happen but in DOMjudge we disallow it. We make the program exit
 * with nonzero exit code if something doesn't work as expected, so we
 * expect WRONG-ANSWER. In particular we *don't* expect RUN-ERROR as
 * this could easily be mixed up with a failure for another reason.
 *
 * @EXPECTED_RESULTS@: WRONG-ANSWER
 */

#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/types.h>
#include <error.h>
#include <errno.h>

const char filename[64] = "my-local-file.txt";

int main(int argc, char **argv)
{
	FILE *f;

	f = fopen(filename, "w");

	if ( f!=NULL ) {
		printf("Unexpected: we were able to open file '%s' for writing.\n", filename);
		return 1;
	}

	f = fopen(filename, "r");

	if ( f!=NULL ) {
		printf("Unexpected: we were able to open file '%s' for reading.\n", filename);
		return 1;
	}

	printf("All checks worked as expected.\n");
	return 0;
}
