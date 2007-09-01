/* $Id$
 *
 * This should give CORRECT on the problem 'fltcmp' (but WRONG ANSWER on 'hello').
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
