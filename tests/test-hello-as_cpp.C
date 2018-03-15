/*
 * This should give COMPILER-ERROR on the default problem 'hello'.
 * We don't accept .C as C++ filename extension.
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
