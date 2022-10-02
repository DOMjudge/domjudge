/*
 * This should fail with a TIMELIMIT.
 *
 * @EXPECTED_RESULTS@: TIMELIMIT
 */

#include <unistd.h>

int main()
{
	while ( 1 ) sleep(1);

	return 0;
}
