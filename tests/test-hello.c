/*
 * This should give CORRECT on the default problem 'hello'.
 *
 * @EXPECTED_RESULTS@: CORRECT
 */

#include <stdio.h>

int main()
{
	char hello[20] = "Hello world!";
#ifdef ONLINE_JUDGE
	printf("%s\n",hello);
#else
	printf("ONLINE_JUDGE not defined\n");
#endif
	return 0;
}
