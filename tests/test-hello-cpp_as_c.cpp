/*
 * This should give COMPILER-ERROR on the default problem 'hello'.
 *
 * The code is correct, but it is C++ code submitted as C. The
 * compiler should reject the .cpp filename extension.
 *
 * @EXPECTED_RESULTS@: COMPILER-ERROR
 */

#include <iostream>
#include <string>

using namespace std;

int main()
{
	string hello("Hello world!");
	cout << hello << endl;
	return 0;
}
