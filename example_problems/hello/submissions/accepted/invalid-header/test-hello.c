/*
 * This should give CORRECT on the default problem 'hello'.
 * The extra, invalid, header-file will not be passed to gcc,
 * since it doesn't have a valid c extension. Thus, it will be
 * ignored during compilation, since it is not included in this file.
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
