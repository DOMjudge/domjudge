#include <stdio.h>

int main()
{
	int aantaltests, test;
	char naam[100];

	scanf("%d\n",&aantaltests);
    
	for(test=1; test<=aantaltests; test++) {        
		scanf("%s\n",naam);
		printf("Hallo %s!\n",naam);
	}

	return 0;
}
