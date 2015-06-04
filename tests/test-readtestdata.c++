/*
 * This tries to read testcase files and output them, so will
 * give CORRECT when this is possible or RUN-ERROR when not.
 *
 * @EXPECTED_RESULTS@: RUN-ERROR
 */

#include <fstream>
#include <iostream>
#include <string>

#include <dirent.h>
#include <errno.h>
#include <unistd.h>
#include <sys/stat.h>
#include <sys/types.h>

using namespace std;

int main()
{
	DIR *dp;
	struct dirent *dirp;
	string dir  = "testcase/";
	string filepath, fline;
	ifstream fin;

	// Try various different levels of higher directories:
	for(int i=0; i<=10; i++) {
		dp = opendir( dir.c_str() );
		if ( dp == NULL ) {
			cerr << "Error (" << errno << "): could not open dir " << dir << endl;
		} else {
			break;
		}
		dir = "../" + dir;
	}
	// Error out if no directory could be opened. This should happen,
	// even without a chroot, as the testcase cache directory should
	// not be accessible nor readable for the domjudge-run user.
	if ( dp == NULL ) return 1;

	while ( (dirp = readdir( dp )) )
	{
		filepath = dir + "/" + dirp->d_name;

		if ( filepath.find("testcase.hello") == std::string::npos) continue;
		if ( filepath.find(".out") == std::string::npos) continue;

		fin.open( filepath.c_str() );
		while (getline (fin,fline)) cout << fline << endl;
		fin.close();
	}

	closedir( dp );

	return 0;
}
