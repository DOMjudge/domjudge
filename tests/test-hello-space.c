/*
 * This should give CORRECT or WRONG-ANSWER on the default problem
 * 'hello' depending on how strict white space is checked for.
 *
 * @EXPECTED_RESULTS@: CORRECT,WRONG-ANSWER
 */

#include <stdio.h>

int main()
{
	char hello[20] = "Hello   	 world!";
	printf("%s\n",hello);
	return 0;
}
