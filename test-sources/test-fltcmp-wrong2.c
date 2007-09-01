/* $Id$
 *
 * This should give WRONG ANSWER on the problem 'fltcmp' with 4 errors
 * on lines 1,2,3,9. 
 */

#include <stdio.h>
#include <math.h>

int main()
{
	int run, nruns;
	double x, y;
	
	scanf("%d\n",&nruns);

	scanf("%lf\n",&x); printf("+2.0\n");
	scanf("%lf\n",&x); printf("%.7lf\n",1/0.0);
	scanf("%lf\n",&x); printf("%lE\n",0.0/-0);
	
	for(run=4; run<=nruns; run++) {
		scanf("%lf\n",&x);
		y = 1/x;
		printf("%.7lf\n",y);
	}

	printf("0.0\n");
	
	return 0;
}
