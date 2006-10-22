/* $Id$
 *
 * This should fail in the default configuration with RUN-ERROR due to 
 * running out of memory, which is restricted. When the memory limit is 
 * set higher, it will give WRONG-ANSWER.
 */

#include <stdio.h>

char a[64*1024*1024];

int main()
{
	a[10] = 1;

	printf("Statically allocated 64 MB.\n");
	
	return 0;
}
