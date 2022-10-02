/*
 * This should give WRONG ANSWER on the problem 'fltcmp' for inputs
 * 0, 1 and INF.
 *
 * @EXPECTED_RESULTS@: WRONG-ANSWER
 */

#include <stdio.h>
#include <math.h>

int main()
{
	int run, nruns;
	double x, y;
	int testinf = 0;

	scanf("%d\n",&nruns);

	for(run=1; run<=nruns; run++) {
		scanf("%lf\n",&x);

		if ( fabs(x-1)<0.0001 ) {
			printf("+2.0\n");
			continue;
		}
		if ( fabs(x)<0.0001 ) {
			testinf = 1;
			printf("%lE\n",0.0/-0);
			continue;
		}
		if ( isinf(x) ) {
			printf("%.7lf\n",1/0.0);
			continue;
		}

		y = 1/x;
		printf("%.7lf\n",y);
	}

	if ( testinf ) printf("0.0\n");

	return 0;
}
