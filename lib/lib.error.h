/*
 * Error handling and logging functions
 *
 * $Id$
 */

#ifndef _LIB_ERROR_
#define _LIB_ERROR_

#include <stdarg.h>
#include <stdio.h>
#include <errno.h>
#include <syslog.h>

#define ERRSTR   "error"
#define ERRMATCH ERRSTR": "

#ifdef __cplusplus
extern "C"
{
#endif /* __cplusplus */

const int exit_failure = -1;

/* Import from the main program for logging purposes */
extern char *progname;

/* Variables defining logmessages verbosity to stderr/logfile */
extern int  verbose;
extern int  loglevel;
extern FILE *stdlog;

void logmsg (int, char *, ...);
void vlogmsg(int, char *, va_list);
/* Logging functions (vlogmsg uses va_list instead of argument list):
 * Logs a message to stderr and/or logfile, including date and program name,
 * depending on the loglevel treshold values.
 *
 * Arguments:
 * int loglevel    syslog loglevel of this log-message
 * char *mesg      message, may include printf output format characters '%'
 * ... or va_list  optional arguments for format characters
 */

char *errorstring(int, char *);
/* Error string generating function:
 * Returns a pointer to a dynamically allocated string containing the error
 * message. Optional arguments still have to be inserted (e.g. by calling
 * vlogmsg).
 *
 * Arguments:
 * int errnum      'errno' value to use for error string output, set 0 to skip
 * char *mesg      message, may include printf output format characters '%'
 *
 * Returns a char pointer to the allocated string.
 */

void logerror (int, char *, ...);
void error    (int, char *, ...);
void warning  (int, char *, ...);
void vlogerror(int, char *, va_list);
void verror   (int, char *, va_list);
void vwarning (int, char *, va_list);
/* Error and warning functions (v.. uses va_list instead of argument list):
 * Logs an error message including error string from 'errno'.
 *   logerror   only logs the error message
 *   error      log the message and exits with exit_failure
 *   warning    log the message and generates extra warning signals
 *
 * Arguments:
 * int errnum      'errno' value to use for error string output, set 0 to skip
 * char *mesg      message, may include printf output format characters '%'
 * ... or va_list  optional arguments for format characters
 */

#ifdef __cplusplus
}
#endif

#endif /* _LIB_ERROR_ */
