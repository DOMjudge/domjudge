/*
 * This should fail with RUN-ERROR due to running out of memory, which
 * is restricted.
 *
 * @EXPECTED_RESULTS@: RUN-ERROR
 */

#include <cstdlib>
#include <vector>

using namespace std;

int main() {
	vector<vector<char>> vs;
	while(true) {
		vector<char> v;
		// Allocate 1MB at a time.
		try {
			v.reserve(1ll * 1024 * 1024);
			vs.push_back(std::move(v));
		} catch(const std::bad_alloc&) {
			// Handle the exception and clean-up manually to prevent slow termination of the
			// program.
			vs.~vector<vector<char>>();
			// Make sure to re-raise the exit with SIGABRT.
			abort();
		}
	}
	return 0;
}
