/*
 * Error handling and logging functions
 */

#ifndef LIB_ERROR_H
#define LIB_ERROR_H

#include <stdarg.h>
#include <stdio.h>
#include <errno.h>
#ifdef HAVE_SYSLOG_H
#include <syslog.h>
#else
#error "Syslog header file not available."
#endif

#define ERRSTR    "error"
#define ERRMATCH  ERRSTR": "

#define WARNSTR   "warning"
#define WARNMATCH WARNSTR": "

#ifdef __cplusplus
extern "C" {
#endif

extern const int exit_failure;

/* Import from the main program for logging purposes */
extern const char *progname;

/* Variables defining logmessages verbosity to stderr/logfile */
extern int  verbose;
extern int  loglevel;
extern FILE *stdlog;

void  logmsg(int, const char *, ...) __attribute__((format (printf, 2, 3)));
void vlogmsg(int, const char *, va_list);
/* Logging functions (vlogmsg uses va_list instead of argument list):
 * Logs a message to stderr and/or logfile, including date and program name,
 * depending on the loglevel treshold values.
 *
 * Arguments:
 * int loglevel    syslog loglevel of this log-message
 * char *mesg      message, may include printf output format characters '%'
 * ... or va_list  optional arguments for format characters
 */

char *errorstring(const char *, int, const char *);
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

void logerror (int, const char *, ...) __attribute__((format (printf, 2, 3)));
void error    (int, const char *, ...) __attribute__((format (printf, 2, 3), noreturn));
void warning  (int, const char *, ...) __attribute__((format (printf, 2, 3)));
void vlogerror(int, const char *, va_list) __attribute__((nonnull (2)));
void verror   (int, const char *, va_list) __attribute__((nonnull (2), noreturn));
void vwarning (int, const char *, va_list) __attribute__((nonnull (2)));
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

char  *allocstr(const char *, ...) __attribute__((format (printf, 1, 2)));
char *vallocstr(const char *, va_list);
/* Create a c-string by allocating memory for it and writing to it,
 * using printf type format characters.
 *
 * Arguments:
 * char *mesg      message, may include printf output format characters '%'
 * ... or va_list  optional arguments for format characters
 *
 * Returns a pointer to the allocated string
 */

#ifdef __cplusplus
}
#endif

#endif /* LIB_ERROR_H */
