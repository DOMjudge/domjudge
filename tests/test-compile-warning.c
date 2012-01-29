/*
 * This should give compiler warnings and fail with NO-OUTPUT
 *
 * @EXPECTED_RESULTS@: NO-OUTPUT
 */

#include <stdio.h>

int main()
{
	char str[1000];

	gets(str);

	return 0;
}
