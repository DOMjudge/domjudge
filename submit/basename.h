/**
 * basename - return the name-within-directory of a file name.
 * Inspired by basename.c from the GNU C Library.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

#include <string.h>

#if defined(__CYGWIN__) || defined(__CYGWIN32__)
#define PATHSEP "\\/"
#else
#define PATHSEP "/"
#endif

char *gnu_basename(const char *filename)
{
	char *p;

	for(p=(char *)filename+strlen(filename)-1; p>=filename; p--) {
		if ( strchr(PATHSEP,*p)!=NULL ) break;
	}

	return p+1;
}
