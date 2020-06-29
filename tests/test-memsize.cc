/*
 * This should fail with RUN-ERROR due to running out of memory, which
 * is restricted.
 *
 * Note: This may try to create a coredump on exit and time out. This
 * can be prevented with `ulimit -c 0`.
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
