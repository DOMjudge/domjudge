/*
 * Miscellaneous common functions for C/C++ programs.
 *
 * $Id$
 */

#ifndef __LIB_MISC_H
#define __LIB_MISC_H

#include <stdarg.h>
#include <sys/wait.h>

#ifdef __cplusplus
extern "C" {
#endif

/* Maximum arguments to execute() */
#define MAXARGS 10

/* Wrapper to call beep with one of the predefined settings. */
#define beep(BEEPTYPE) system(BEEP_CMD" "BEEPTYPE" &")

char  *allocstr(const char *, ...);
char *vallocstr(const char *, va_list);
/* Create a c-string by allocating memory for it and writing to it,
 * using printf type format characters.
 *
 * Arguments:
 * char *mesg      message to write, may include printf format characters '%'
 * ... or va_list  optional arguments for format characters
 *
 * Returns a pointer to the allocated string
 */

int execute(const char *, char **, int, int[3], int);
/* Execute a subprocess using fork and execvp and optionally perform
 * IO redirection of stdin/stdout/stderr.
 *
 * Arguments:
 * char *cmd        command to be executed (PATH is searched)
 * char *args[]     array of arguments to command
 * int nargs        number of arguments specified
 * int stdio_fd[3]  File descriptors for stdin, stdout and stderr respectively.
 *                    Set any combination of these to non-zero to redirect IO
 *                    for those. each non-zero element will be set to a file
 *                    descriptor pointing to a pipe to the respective stdio's
 *                    of the command.
 * int err2out      Set non-zero to redirect command stderr to stdout. When set
 *                    the redirection of stderr by stdio_fd[2] is ignored.
 *
 * Returns:
 * On errors from system calls -1 is returned: check errno for extra information.
 * On internal errors -2 is returned.
 *
 * When no redirection is done (except for err2out) waits for the command to
 * finish and returns exitcode (or bash like exitcode on abnormal program
 * termination.
 *
 * When redirection is done, returns immediately after starting the command
 * with the process-ID of the child command.
 */

void initsignals();
/* Installs a signal handler to gracefully terminate daemon programs
 * upon receiving TERMINATE, HANGUP and INTERRUPT signals which sets
 * 'extern int exitsignalled = 1'. The sleep() call will automatically
 * return on receiving a signal.
  */

#ifdef __cplusplus
}
#endif

#endif /* __LIB_MISC_H */
