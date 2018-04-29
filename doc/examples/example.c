#include <stdio.h>

int main() {
	int i, ntests;
	char name[100];

	scanf("%d\n", &ntests);

	for (i = 0; i < ntests; i++) {
		scanf("%s\n", name);
		printf("Hello %s!\n", name);
	}
}
