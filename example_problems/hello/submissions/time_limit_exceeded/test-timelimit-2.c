/*
 * This should give a TIMELIMIT.
 *
 * This tests remapping from TIME_LIMIT_EXCEEDED to TIMELIMIT.
 *
 * @EXPECTED_RESULTS@: TIME_LIMIT_EXCEEDED
 */

int main()
{
	int a = 0;

	while ( 1 ) a++;

	return 0;
}
