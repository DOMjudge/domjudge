/**
 * basename - return the name-within-directory of a file name.
 * Inspired by basename.c from the GNU C Library.
 *
 * Part of the DOMjudge Programming Contest Jury System and licensed
 * under the GNU GPL. See README and COPYING for details.
 */

#include <string.h>

#if defined(__CYGWIN__) || defined(__CYGWIN32__)
#define PATHSEP "\\/"
#else
#define PATHSEP "/"
#endif

const char *gnu_basename(const char *filename)
{
	const char *p;

	for(p=filename+strlen(filename)-1; p>=filename; p--) {
		if ( strchr(PATHSEP,*p)!=NULL ) break;
	}

	return p+1;
}
