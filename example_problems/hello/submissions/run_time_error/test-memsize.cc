/*
 * This should fail with RUN-ERROR due to running out of memory, which
 * is restricted.
 *
 * Note: If running this locally, first restrict memory by running
 * e.g. `ulimit -v 2000000` for 2GB, and make sure coredumps are
 * disabled by running `ulimit -c 0` to make it faster.
 *
 * @EXPECTED_RESULTS@: RUN-ERROR
 */

#include <iostream>
#include <vector>

using namespace std;

const size_t inc_mb = 128;

int main() {
	vector<vector<char>> vs;
	while(true) {
		vs.emplace_back(inc_mb * 1024 * 1024);
		std::cerr << "Allocated: " << inc_mb * vs.size() << " MB" << std::endl;
	}
	return 0;
}
