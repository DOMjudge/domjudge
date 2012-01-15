/*
 * Sample solution in C for the "boolfind" interactive problem.
 * This program uses scanf and should hang on reading input,
 * resulting in a timelimit verdict.
 *
 * @EXPECTED_RESULTS@: TIMELIMIT
 */

#include <stdio.h>
#include <string.h>

int run, nruns;
long long n, lo, hi, mid;
char answer[10];
char buf[256];
int ch;

int main()
{
	scanf("%d\n",&nruns);

	for(run=1; run<=nruns; run++) {

		scanf("%lld\n",&n);

		lo = 0;
		hi = n;
		while ( lo+1<hi ) {
			mid = (lo+hi)/2;
			printf("READ %lld\n",mid); fflush(NULL);

			scanf("%8s\n",answer);
			if ( strcmp(answer,"true")==0 ) {
				lo = mid;
			} else if ( strcmp(answer,"false")==0 ) {
				hi = mid;
			} else {
				printf("invalid return value '%s'\n",answer);
				return 1;
			}
		}
		printf("OUTPUT %lld\n",lo); fflush(NULL);
	}

	return 0;
}
