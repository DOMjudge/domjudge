/* $Id$
 *
 * This should give CORRECT on the default problem 'hello'.
 *
 * @EXPECTED_RESULTS@: CORRECT
 */

#include <stdio.h>

int main()
{
	char hello[20] = "Hello world!";
	printf("%s\n",hello);
	return 0;
}
