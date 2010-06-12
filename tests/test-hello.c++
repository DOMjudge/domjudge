/* $Id$
 *
 * This should give CORRECT on the default problem 'hello'.
 *
 * @EXPECTED_RESULTS@: CORRECT
 */

using namespace std;

#include <iostream>
#include <string>

int main()
{
	string hello("Hello world!");
	cout << hello << endl;
	return 0;
}
