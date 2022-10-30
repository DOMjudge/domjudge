/*
 * Writes very long lines to stdout. Should give WRONG-ANSWER, but not
 * crash the system.
 *
 * @EXPECTED_RESULTS@: WRONG-ANSWER
 */

#include <stdio.h>

/* Keep nlines*linelen below source size limit: */
const int linelen = 131070;
const int nlines = 2;

int main()
{
	int c, i;

	for(c=0; c<nlines; c++) {
		for(i=0; i<linelen; i++) putchar('0'+c);
		putchar('\n');
	}

	return 0;
}
