/*
 * Common socket send/receive code for submit and submitdaemon programs.
 *
 * $Id$
 */

#ifndef SUBMITCOMMON_HXX
#define SUBMITCOMMON_HXX

#include <cstdarg>
#include <string>

#define FAILURE 0
#define SUCCESS 1
#define WARNING 2

#define FAILURE_EXITCODE -1
#define SUCCESS_EXITCODE  0
#define WARNING_EXITCODE  1

#define SOCKETBUFFERSIZE 256

/* Buffer where the last received message is stored */
extern char lastmesg[];

void vsendit(int, const char *, va_list);
void  sendit(int, const char *, ...);
/* Send a message over a socket and log it (va_list and argument list versions).
 *
 * Arguments:
 * int fd          filedescriptor of the socket
 * char *mesg      message to write, may include printf format characters '%'
 * va_list or ...  optional arguments for format characters
 */

void senderror  (int fd, int errnum, const char *mesg, ...);
void sendwarning(int fd, int errnum, const char *mesg, ...);
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

std::string stringtolower(std::string);
/* Convert a C++ string to lowercase.
 *
 * Arguments:
 * string str  string to convert to lowercase
 *
 * Returns a copy of str, converted to lowercase
 */

#endif /* SUBMITCOMMON_HXX */
