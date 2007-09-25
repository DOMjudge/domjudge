/*
 * Error handling and logging functions
 *
 * $Id$
 */

#ifndef __LIB_ERROR_H
#define __LIB_ERROR_H

#include <stdarg.h>
#include <stdio.h>
#include <syslog.h>
#include <errno.h>

#define ERRSTR    "error"
#define ERRMATCH  ERRSTR": "

#define WARNSTR   "warning"
#define WARNMATCH WARNSTR": "

#ifdef __cplusplus
extern "C" {
#endif

extern const int exit_failure;

/* Import from the main program for logging purposes */
extern char *progname;

/* Variables defining logmessages verbosity to stderr/logfile */
extern int  verbose;
extern int  loglevel;
extern FILE *stdlog;

/* Nonzero if (v)logmsg should log to syslog as well. Nonzero by default. */
extern int  logmsg_uses_syslog;

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

char *errorstring(char *, int, char *);
/* Error string generating function:
 * Returns a pointer to a dynamically allocated string containing the error
 * message.
 *
 * Arguments:
 * char *type      string of type of message: ERRSTR, WARNSTR or custom.
 * int errnum      'errno' value to use for error string output, set 0 to skip
 * char *mesg      optional accompanying message to display, set NULL to skip
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
 *   logerror   only logs the error message (non-fatal error)
 *   error      log the message and exits with exit_failure
 *   warning    same as 'logerror', but prints 'warning' instead of 'error'
 *
 * Arguments:
 * int errnum      'errno' value to use for error string output, set 0 to skip
 * char *mesg      message, may include printf output format characters '%'
 * ... or va_list  optional arguments for format characters
 */

#ifdef __cplusplus
}
#endif

#endif /* __LIB_ERROR_H */
