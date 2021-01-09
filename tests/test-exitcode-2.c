/*
 * This should fail with RUN-ERROR and tests remapping of error names (due to exitcode != 0)
 *
 * @EXPECTED_RESULTS@: RUN_TIME_ERROR
 */

int main()
{
	return 1;
}
