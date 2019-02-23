/*
 * Miscellaneous common functions for C/C++ programs.
 */

#ifndef LIB_MISC_H
#define LIB_MISC_H

#ifdef __cplusplus
extern "C" {
#endif

/* I/O redirection options for execute() */
#define FDREDIR_NONE -1
#define FDREDIR_PIPE -2

/* Define wrapper around true function '_alert' to allow passing
 * LIBDIR as defined in calling program. */
#define alert(msgtype,description) _alert(LIBDIR,msgtype,description)

void _alert(const char *libdir, const char *msgtype, const char *description)
    __attribute__((nonnull (1, 2)));
/* Execute 'alert' plugin program to perform user configurable action
 * on important system events. See default alert script for more details.
 */

int execute(const char *, const char **, int, int[3], int)
    __attribute__((nonnull (1, 2)));
/* Execute a subprocess using fork and execvp and optionally perform
 * IO redirection of stdin/stdout/stderr.
 *
 * Arguments:
 * char *cmd        command to be executed (PATH is searched)
 * char *args[]     array of arguments to command
 * int nargs        number of arguments specified
 * int stdio_fd[3]  File descriptors for stdin, stdout and stderr respectively.
 *                    Each can separately be set to one of the following:
 *                      FDREDIR_NONE - don't do redirection
 *                      FDREDIR_PIPE - connect to pipe and set value to file
 *                                     descriptor of other end of the pipe
 *                      fd >= 0      - make this a duplicate of <fd>
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

void daemonize(const char *);
/* Forks and detaches the current process to run as a daemon. Similar
 * to the daemon() call present in Linux and *BSD, but implented here,
 * because it is not specified by POSIX, SUSv2 or SVr4.
 *
 * Arguments:
 * char *pidfile    pidfile to check for running instances and write PID;
 *                    set to NULL to not use a pidfile.
 *
 * Either returns successfully or exits with an error.
 */

char *stripendline(char *) __attribute__((nonnull (1)));
/* Removes end-of-line characters (CR and LF) from string. Returns the
 * original pointer to the modified string. */

void version(const char *, const char *) __attribute__((nonnull (1, 2)));
/* Print standard program name and version, with disclaimer and GPL
 * licence info. Arguments: program name and version strings.
 */

#ifdef __cplusplus
}
#endif

#endif /* LIB_MISC_H */
