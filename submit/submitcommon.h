/*
 * Common socket send/receive code for submit and submitdaemon programs.
 *
 * $Id$
 */

#ifndef __SUBMITCOMMON_H
#define __SUBMITCOMMON_H

#include <stdarg.h>
#include <string>

/* Logging and error functions */
#include "../lib/lib.error.h"

#define SOCKETBUFFERSIZE 256

#define MAXARGS 10

#define PIN  1
#define POUT 0

const int FAILURE = 0;
const int SUCCESS = 1;
const int WARNING = 2;

const int FAILURE_EXITCODE = -1;
const int SUCCESS_EXITCODE = 0;
const int WARNING_EXITCODE = 1;

/* Buffer where the last received message is stored */
extern char lastmesg[];

void vsendit(int, char *, va_list);
void  sendit(int, char *, ...);
/* Send a message over a socket and log it (va_list and argument list versions).
 *
 * Arguments:
 * int fd          filedescriptor of the socket
 * char *mesg      message to write, may include printf format characters '%'
 * va_list or ...  optional arguments for format characters
 */

void senderror  (int fd, int errnum, char *mesg, ...);
void sendwarning(int fd, int errnum, char *mesg, ...);
/* Send an error/warning message over a socket using sendit, close the
 * socket and generate an error/warning.
 *
 * Arguments:
 * int fd      filedescriptor of the socket
 * int errnum  'errno' value to use for error string output, set 0 to skip
 * char *mesg  message to write, may include printf format characters '%'
 * ...         optional arguments for format characters
 */

int receive(int);
/* Receive a message over a socket and log it.
 * Message is put in 'lastmesg' and number of characters read is returned.
 *
 * Arguments:
 * int fd      filedescriptor of the socket
 */

char *allocstr(char *, ...);
/* Create a c-string by allocating memory for it and writing to it,
 * using printf type format characters.
 *
 * Arguments:
 * char *mesg  message to write, may include printf format characters '%'
 * ...         optional arguments for format characters
 *
 * Returns a pointer to the allocated string
 */

std::string stringtolower(std::string);
/* Convert a C++ string to lowercase.
 *
 * Arguments:
 * string str  string to convert to lowercase
 *
 * Returns a copy of str, converted to lowercase
 */

int execute(char *, char **, int , int[3], int );
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

#endif /* __SUBMITCOMMON_H */
