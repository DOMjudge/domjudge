/* $Id$
 *
 * This allocates stack memory and should fail with RUN-ERROR due to
 * running out of memory, which is restricted.
 */

#include <stdio.h>
#include <stdlib.h>

void recurse_malloc(int depth)
{
	char a[1024*1024];
	
	depth++;
	
	printf("Allocated %3d MB stack memory.\n",depth);

	fflush(stdout);
	
	recurse_malloc(depth);
}

int main()
{
	recurse_malloc(0);
	
	return 0;
}
