/*
 * Sample solution in C for the "boolfind" interactive problem.
 *
 * @EXPECTED_RESULTS@: CORRECT
 */

#include <stdio.h>
#include <string.h>

int run, nruns;
long long n, lo, hi, mid;
char answer[10];
char buf[256];
int ch;

/* Be careful not to read closing newlines as this will trigger the
 * call scanf to hang (trying to gobble up more whitespace?). Also we
 * flush output everytime.
 */
int main()
{
	scanf("%d",&nruns);

	for(run=1; run<=nruns; run++) {

		scanf("%lld",&n);

		lo = 0;
		hi = n;
		while ( lo+1<hi ) {
			mid = (lo+hi)/2;
			printf("READ %lld\n",mid); fflush(NULL);

			scanf("%8s",answer);
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
