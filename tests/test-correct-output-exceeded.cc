/*
 * This first prints the correct answer and then outputs lots of
 * whitespace, surpassing the default output limit. Current behaviour
 * is that an output limit error is generated.
 *
 * @EXPECTED_RESULTS@: OUTPUT-LIMIT
 */

#include <iostream>
#include <string>

using namespace std;

int main()
{
	string hello("Hello world!");
	cout << hello << endl;

	// Now print lots of whitespace:
	for(int i=0; i<10*1024*1024; i++) {
		if ( i%1000==0 ) cout << endl;
		cout << ' ';
	}
	cout << endl;

	return 0;
}
