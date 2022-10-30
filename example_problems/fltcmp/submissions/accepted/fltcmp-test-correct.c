/*
 * This should give CORRECT on the problem 'fltcmp' (but WRONG ANSWER on 'hello').
 *
 * @EXPECTED_RESULTS@: CORRECT
 */

#include <stdio.h>
#include <math.h>

int main()
{
	int run, nruns;
	double x, y;

	scanf("%d\n",&nruns);

	for(run=1; run<=nruns; run++) {
		scanf("%lf\n",&x);
		y = 1/x;
		printf("%.7lf\n",y);
	}

	return 0;
}
