/*
 * Sample solution in C++ for the "boolfind" interactive problem.
 * Gives correct answer, but with additional cruft on each line, so
 * should fail.
 *
 * @EXPECTED_RESULTS@: WRONG-ANSWER
 */

#include <iostream>
#include <string>

using namespace std;

int run, nruns;
long long n, lo, hi, mid;
string answer;

int main()
{
	cin >> nruns;

	for(run=1; run<=nruns; run++) {

		cin >> n;

		lo = 0;
		hi = n;
		while ( lo+1<hi ) {
			mid = (lo+hi)/2;
			cout << "READ " << mid << endl;
			cin >> answer;
			if ( answer=="true" ) {
				lo = mid;
			} else if ( answer=="false" ) {
				hi = mid;
			} else {
				cout << "invalid return value '" << answer << "'\n";
				return 1;
			}
		}
		cout << "OUTPUT " << lo << "  0" << endl;
	}

	cout << "THIS IS EXTRA DATA ON A FINAL LINE, NO NEWLINE!";

	return 0;
}
