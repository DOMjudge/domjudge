using namespace std;

#include <iostream>
#include <vector>

vector<char> a;

int main()
{
	int i;

	/*
	  Watch out: resizing of a vector allocates AT LEAST that much memory!
	  Testing shows, that (glibc 2.2.5) twice the requested amount is
	  allocated, so e.g. when you have 64 MB memory available, already when
	  resizing to more than 32 MB, you run out of memory.
	 */
	for(i=0; 1; i++) {
		a.resize(i*1024*1024,0);
		cout << "memory allocated: " << i << " MB." << endl;
	}

	return 0;
}
