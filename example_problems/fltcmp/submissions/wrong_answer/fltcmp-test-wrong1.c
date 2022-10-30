/*
 * This should give WRONG ANSWER on the problem 'fltcmp' with
 * precision errors.
 *
 * @EXPECTED_RESULTS@: WRONG-ANSWER
 */

#include <stdio.h>
#include <math.h>

int main()
{
	int run, nruns;
	double x, y;
	double epsilon = 0.0001;

	scanf("%d\n",&nruns);

	for(run=1; run<=nruns; run++) {
		scanf("%lf\n",&x);
		y = 1/x + epsilon;
		epsilon /= 6;
		printf("%.7lf\n",y);
	}

	return 0;
}
