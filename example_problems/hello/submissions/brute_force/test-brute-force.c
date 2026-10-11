/*
 * This takes far too long before printing the answer. It has no
 * expected results annotation: the brute_force directory of the
 * problem package format allows either TIMELIMIT or RUN-ERROR.
 */

#include <stdio.h>

int main()
{
	volatile unsigned long long i;

	for (i = 0; i < 1000000000000ULL; i++);

	printf("Hello world!\n");
	return 0;
}
