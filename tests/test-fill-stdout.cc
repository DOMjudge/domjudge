/*
 * Floods stdout and should fail with TIMELIMIT, since output is
 * automatically truncated at the filesize limit.
 *
 * @EXPECTED_RESULTS@: TIMELIMIT
 */

#include <iostream>
#include <string>

int main()
{
	while ( 1 ) std::cout << "Fill stdout with nonsense, to test filesystem stability.\n";

	return 0;
}
