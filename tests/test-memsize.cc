/*
 * This should fail with RUN-ERROR due to running out of memory, which
 * is restricted. The amount allocated may seem to be half of the
 * available, but that is because (GNU implementation) STL vectors by
 * default allocate double the amount of memory requested.
 *
 * @EXPECTED_RESULTS@: RUN-ERROR
 */

#include <iostream>
#include <vector>

using namespace std;

vector<char> a;

int main()
{
	int p, i;

	/*
	  Watch out: resizing of a vector allocates AT LEAST that much memory!
	  Testing shows, that (glibc 2.2.5) twice the requested amount is
	  allocated, so e.g. when you have 64 MB memory available, already when
	  resizing to more than 32 MB, you run out of memory.
	*/
	for(p=4; 1; p*=2) {
		for(i=p/2; i<p; i+=p/4) {
			cout << "trying to allocate " << i << " MB... ";
			a.resize(i*1024*1024,0);
			if ( a.capacity()<i*1024*1024 ) {
				cout << "resizing failed." << endl;
				return 0;
			}
			for(int j=a.capacity()/2; j<a.capacity(); j += 512) a[j] = j%137;
			cout << "allocated: " << a.capacity()/1024/1024 << " MB." << endl;
		}
	}

	return 0;
}
