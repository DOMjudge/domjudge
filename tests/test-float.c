/*
 * This should fail with WRONG-ANSWER (C doesn't give floating point
 * errors when dividing by zero).
 *
 * @EXPECTED_RESULTS@: WRONG-ANSWER
 */

#include <stdio.h>
#include <math.h>

/* To have M_PI constant available in ANSI C. */
#ifndef M_PI
#define M_PI 3.14159265358979323846
#endif

int main()
{
	double a = M_PI/2;
	double b;

	b = tan(a);
	a = exp(b);

	printf("%lf\n%lf\n%lf\n%lf\n",b,a,1/a,acos(b));

	return 0;
}
