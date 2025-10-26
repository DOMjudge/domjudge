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


void version(const char *, const char *) __attribute__((nonnull (1, 2)));
/* Print standard program name and version, with disclaimer and GPL
 * licence info. Arguments: program name and version strings.
 */

#ifdef __cplusplus
}
#endif

#endif /* LIB_MISC_H */
