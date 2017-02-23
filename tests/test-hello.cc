/*
 * This should give CORRECT on the default problem 'hello'.
 *
 * @EXPECTED_RESULTS@: CORRECT
 */

#include <iostream>
#include <string>

using namespace std;

int main()
{
	string hello("Hello world!");
#ifdef DOMJUDGE
	cout << hello << endl;
#else
	printf("DOMJUDGE not defined\n");
#endif
	return 0;
}
