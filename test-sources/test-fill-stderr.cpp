/* $Id$
 *
 * Floods stderr and should fail with TIME-LIMIT or RUN-ERROR
 * depending on whether timelimit or filesize limit is reached first.
 */

using namespace std;

#include <iostream>
#include <string>

int main()
{
	while ( 1 ) cerr << "Fill stderr with nonsense, to test filesystem stability.\n";

	return 0;
}
