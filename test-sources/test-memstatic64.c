#include <stdio.h>

char a[64*1024*1024];

int main()
{
	a[10] = 1;

	printf("Statically allocated 64 MB.\n");
	
	return 0;
}
