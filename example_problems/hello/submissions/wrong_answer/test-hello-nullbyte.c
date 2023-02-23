/*
 * This should give WRONG-ANSWER on the default problem 'hello'.
 * Note that there was a bug in the default validator where it would
 * ignore null bytes and thus report this as CORRECT.
 *
 * @EXPECTED_RESULTS@: WRONG-ANSWER
 */

#include <stdio.h>

int main()
{
	printf("Hello world!%cExtra_stuff_that_should_trigger_wrong-answer...\n", 0);
	return 0;
}
