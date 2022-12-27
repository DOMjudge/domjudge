#include <utility>
#include <string>
#include <sstream>
#include <cassert>
#include <cstring>
#include <cmath>
#include <unistd.h>
#include <vector>
#include "validate.h"

using namespace std;

void check_case(int run) {
	int nbools;
	assert(judge_in >> nbools);
	cout << nbools << endl;

	vector<int> bools(nbools);
	for (int pos = 0; pos < nbools; pos++) {
		assert(judge_in >> bools[pos]);
	}

	int nquery = 0;
	while (true) {
		nquery++;
		string operation;
		if (!(author_out >> operation)) {
			wrong_answer("testcase %d: Cannot parse operation in %dth query.\n", run, nquery);
		}
		int pos;
		if (!(author_out >> pos)) {
			wrong_answer("testcase %d: Cannot parse position in %dth query.\n", run, nquery);
		}
		if (pos < 0 || pos >= nbools) {
			wrong_answer("testcase %d: position %d out of range\n", run, pos);
		}
		if (operation == "OUTPUT") {
			if (pos >= nbools - 1) {
				wrong_answer("testcase %d: position %d out of range for OUTPUT\n", run, pos);
			}
			if (!bools[pos] || bools[pos + 1]) {
				wrong_answer("testcase %d: wrong output, position %d,%d = %d,%d\n", run, pos, pos+1, bools[pos], bools[pos+1]);
			}
			// The team made the correct guess, moving on to the new case.
			break;
		} else if (operation == "READ") {
			// Simulate slow operation by sleeping for 0.1ms
			usleep(100);
			cout << (bools[pos] ? "true" : "false") << endl;
		} else {
			wrong_answer("testcase %d: Unknown instruction '%s'.\n", run, operation.c_str());
		}
	}
}

int main(int argc, char **argv) {
	init_io(argc, argv);

	int nruns;
	assert(judge_in >> nruns);
	cout << nruns << endl;

	for (int run = 1; run <= nruns; run++) {
		check_case(run);
	}

	// Check for trailing output.
	string trash;
	if (author_out >> trash) {
		wrong_answer("Trailing output: '%s'\n", trash.c_str());
	}

	// Yay!
	accept();
}
