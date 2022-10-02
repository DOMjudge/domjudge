/*
 * This program test binary output and should give WRONG-ANSWER, but
 * display diff correctly.
 *
 * @EXPECTED_RESULTS@: WRONG-ANSWER
 */

#include <stdio.h>

#define min(a,b) ((a)<(b) ? (a) : (b))

int main()
{
	int c;

	printf("testing all characters...");
	for(c=0; c<256; c++) {
		if ( c%10==0 ) printf("\ntesting characters %d-%d...\n",c,min(c+9,255));
		printf("%c",c);
	}
	printf("\ndone.\n");

	return 0;
}
