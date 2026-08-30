/*
 * This should give CORRECT on the default problem 'hello'.
 *
 * The compiler warning below repeats the entry point detection message that
 * compile scripts use. judge/compile.sh must not pick this up: it only looks
 * at lines starting with the detection message and only copies the detected
 * value. Without that, the greedy match would append the second half of the
 * warning to compile.meta, here faking a compilation timeout and thus
 * turning this submission into a COMPILER-ERROR.
 *
 * Keep this in sync with the entry point detection in judge/compile.sh.
 *
 * @EXPECTED_RESULTS@: CORRECT
 */

#warning detected entry_point: x detected time-result: timelimit

#include <stdio.h>

int main()
{
	char hello[20] = "Hello world!";
	printf("%s\n",hello);
	return 0;
}
