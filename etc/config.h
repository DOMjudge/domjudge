/* Configuration file for C/C++ programs
 * $Id$
 */

#ifndef _LOCAL_CONFIG_
#define _LOCAL_CONFIG_

#define HOME "/home/cies/nkp0405"

/* Root-paths for different parts of the system. */
#define SYSTEM_ROOT HOME"/systeem/svn/jury"
#define OUTPUT_ROOT HOME"/systeem/systest"
#define INPUT_ROOT  HOME"/opgaven"

/* Paths within OUTPUT_ROOT */
#define INCOMINGDIR OUTPUT_ROOT"/incoming"
#define SUBMITDIR   OUTPUT_ROOT"/sources"
#define JUDGEDIR    OUTPUT_ROOT"/judging"
#define LOGDIR      OUTPUT_ROOT"/log"

/* Absolute path prefix within which chroot is allowed. */
#define CHROOT_PREFIX JUDGEDIR

/* User ID's under which programs are allowed to run.
   End list with -1, UID 0 (root) is ignored. */
const int valid_uid[5] = {5051,65534,9013,-1};

/* TCP port on which submitdaemon listens. */
#define SUBMITPORT   9147
#define SUBMITSERVER "square.a-eskwadraat.nl"

/* Directory where submit-client puts files for sending (relative to $HOME). */
#define USERSUBMITDIR ".submit"

/* Warn user when submission file modifications are older than (in minutes) */
#define WARN_MTIME 5

/* Maximum size of solution source code in KB */
#define SOURCESIZE 256

#endif /* _LOCAL_CONFIG_ */
