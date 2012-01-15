/*
 * Floods stderr and should fail with TIME-LIMIT or RUN-ERROR
 * depending on whether timelimit or filesize limit is reached first.
 *
 * @EXPECTED_RESULTS@: TIME-LIMIT,RUN-ERROR
 */

#include <iostream>
#include <string>

int main()
{
	while ( 1 ) std::cerr << "Fill stderr with nonsense, to test filesystem stability.\n";

	return 0;
}
