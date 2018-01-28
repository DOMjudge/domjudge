/*
 * This should give WRONG-ANSWER on the default problem 'hello'.
 *
 * While we define ONLINE_JUDGE in master, we don't do this for the World
 * Finals.
 *
 * @EXPECTED_RESULTS@: WRONG-ANSWER
 */

#include <stdio.h>

int main(int argc, char **argv)
{
	int i;
	char hello[20] = "Hello world!";
#ifdef ONLINE_JUDGE
	printf("%s\n",hello);
#else
	printf("ONLINE_JUDGE not defined\n");
#endif
	if ( argc>1 ) {
		printf("%d arguments:",argc-1);
		for(i=1; i<argc; i++) printf(" '%s'",argv[i]);
		printf("\n");
	}
	return 0;
}
