/*
 * Tests that we correctly abort submissions that do not write out incorrect
 * results, i.e. where the jury program keeps on reading.
 *
 * @EXPECTED_RESULTS@: TIMELIMIT
 */
#include <iostream>

using namespace std;

int main() {
	while (true) {
		cout << "READ 0" << endl;
	}
	return 0;
}
