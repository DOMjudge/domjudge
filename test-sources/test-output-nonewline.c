/* $Id: test-slow-output.c 884 2005-10-10 17:57:31Z eldering $
 *
 * Writes one line of output without a trailing newline. This should
 * give WRONG-ANSWER and the diff output should show the line.
 */

#include <stdio.h>

int main()
{
	printf("This line has no trailing newline");
	
	return 0;
}
