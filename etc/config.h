/* Configuration file for C/C++ programs
 * $Id$
 */

#ifndef _LOCAL_CONFIG_
#define _LOCAL_CONFIG_

/* Absolute path prefix within which chroot is allowed. */
#define ROOT_PREFIX "/home/cies/nkp0405/"

/* User ID's under which programs are allowed to run.
   End list with -1, UID 0 (root) is ignored. */
const int valid_uid[5] = {5051,65534,9013,-1};

#endif /* _LOCAL_CONFIG_ */
