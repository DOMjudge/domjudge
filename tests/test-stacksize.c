/*
 * This allocates stack memory and should fail with RUN-ERROR due to
 * running out of memory, which is restricted.
 *
 * @EXPECTED_RESULTS@: RUN-ERROR
 */

#include <stdio.h>
#include <stdlib.h>
#include <string.h>

const int mb   = 1024*1024;

void recurse_malloc(int depth)
{
	char a[mb];
	int i, j;

	depth++;

	/* Here we fill the array and do some random reads and writes to
	 * prevent the compiler from optimizing away the array a. */
	memset(a,0,mb);
	i = rand() % mb;
	j = rand() % mb;
	a[i] = depth;

	printf("Allocated %3d MB stack memory, foo = %d.\n",depth,a[j]);

	fflush(stdout);

	recurse_malloc(depth);
}

int main()
{
	recurse_malloc(0);

	return 0;
}
