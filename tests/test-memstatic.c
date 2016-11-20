/*
 * This should fail in the default configuration with RUN-ERROR due to
 * running out of memory, which is restricted. When the memory limit is
 * set higher, it will give WRONG-ANSWER.
 *
 * @EXPECTED_RESULTS@: RUN-ERROR,WRONG-ANSWER
 */

#include <stdio.h>
#include <string.h>

#define size 512

char a[size*1024*1024];

int main()
{
	memset(a,0,size*1024*1024);

	a[10] = 1;

	printf("Statically allocated %d MB.\n",size);

	return 0;
}
