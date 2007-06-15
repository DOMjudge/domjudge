#include <stdio.h>

int main()
{
	int ntests, test;
	char name[100];

	scanf("%d\n", &ntests);
    
	for (test=1; test <= ntests; test++) {        
		scanf("%s\n", name);
		printf("Hello %s!\n", name);
	}

	return 0;
}
