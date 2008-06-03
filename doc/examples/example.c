#include <stdio.h>

int main()
{
	int n;
	char name[100];

	scanf("%d\n", &n);

	while (n-- > 0) {
		scanf("%s\n", name);
		printf("Hello %s!\n", name);
	}

	return 0;
}
