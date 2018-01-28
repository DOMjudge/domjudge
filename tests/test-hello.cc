/*
 * This should give WRONG-ANSWER on the default problem 'hello'.
 * 
 * While we define DOMJUDGE in master, we don't do this for the World
 * Finals.
 *
 * @EXPECTED_RESULTS@: WRONG-ANSWER
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
