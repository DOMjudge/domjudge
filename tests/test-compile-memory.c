/*
 * This program should fail with a COMPILER-ERROR due to a timeout.
 *
 * The program reads from the "infinite" file /dev/zero. This may
 * cause the compiler to use up a large amount of memory.
 *
 * @EXPECTED_RESULTS@: COMPILER-ERROR
 */

#include "/dev/zero"

int main()
{
	return 0;
}
