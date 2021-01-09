/*
 * This should fail with RUN-ERROR and tests remapping of error names and string splitting (due to exitcode != 0)
 *
 * @EXPECTED_RESULTS@: RUN_TIME_ERROR,ACCEPTED
 */

int main()
{
	return 1;
}
