/*
 * This should give CORRECT on the default problem 'hello'.
 *
 * @EXPECTED_RESULTS@: CORRECT
 */

#include <stdio.h>

int main(int argc, char **argv)
{
	int i;
	char hello[20] = "Hello world!";
	printf("%s\n",hello);
	if ( argc>1 ) {
		printf("%d arguments:",argc-1);
		for(i=1; i<argc; i++) printf(" '%s'",argv[i]);
		printf("\n");
	}
	return 0;
}
