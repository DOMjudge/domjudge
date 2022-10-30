/*
 * This should fail in the default configuration with RUN-ERROR due to
 * running out of memory, which is restricted. When the memory limit is
 * set higher, it will give WRONG-ANSWER. Note that the array a is
 * stored inside the binary. If the script filesize limit is set too
 * low, it will result in a COMPILER-ERROR.
 *
 * @EXPECTED_RESULTS@: RUN-ERROR,WRONG-ANSWER
 */

#include <iostream>

#define size 512

char a[size*1024*1024] = { 1 };

int main()
{
	a[10] = -1;

	std::cout << "Statically initialized " << size << " MB.\n";

	return 0;
}
