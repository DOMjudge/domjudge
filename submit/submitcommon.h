/*
 * Common socket send/receive code for submit and submitdaemon programs.
 *
 * $Id$
 */

#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <ctype.h>
#include <string.h>
#include <sys/wait.h>
#include <string>

/* Logging and error functions */
#include "../lib/lib.error.h"

#define SOCKETBUFFERSIZE 256

#define MAXARGS 10

#define PIN  1
#define POUT 0

/* Buffer where the last received message is stored */
char lastmesg[SOCKETBUFFERSIZE];

void version();
/* Print program version, authors, etc. in GNU style */

void vsendit(int, char *, va_list);
void  sendit(int, char *, ...);
/* Send a message over a socket and log it (va_list and argument list versions).
 *
 * Arguments:
 * int fd          filedescriptor of the socket
 * char *mesg      message to write, may include printf format characters '%'
 * va_list or ...  optional arguments for format characters
 */

void senderror(int fd, int errnum, char *mesg, ...);
/* Send an error message over a socket using sendit, close the socket and
 * generate an error.
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

string stringtolower(string);
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


/* ========================= IMPLEMENTATION ============================== */

void version()
{
	printf("%s %s\nWritten by %s\n\n",DOMJUDGE_PROGRAM,PROGRAM,AUTHORS);
	printf(
"%s comes with ABSOLUTELY NO WARRANTY.  This is free software, and you\n"
"are welcome to redistribute it under certain conditions.  See the GNU\n"
"General Public Licence for details.\n",PROGRAM);
	exit(0);
}

void vsendit(int fd, char *mesg, va_list ap)
{
	char buffer[SOCKETBUFFERSIZE];

	vsnprintf(buffer,SOCKETBUFFERSIZE-2,mesg,ap);
	
	logmsg(LOG_DEBUG,"send: %s",buffer);
	
	strcat(buffer,"\015\012");
	
	if ( write(fd,buffer,strlen(buffer))<0 ) error(errno,"writing to socket");
}

void sendit(int fd, char *mesg, ...)
{
	va_list ap;

	va_start(ap,mesg);
	vsendit(fd,mesg,ap);
	va_end(ap);
}

void senderror(int fd, int errnum, char *mesg, ...)
{
	va_list ap;
	char *buf, *tmp;

	tmp = errorstring(errnum,mesg);

	buf = (char *) malloc(strlen(tmp)+2);
	if ( buf==NULL ) abort();

	buf[0] = '-';
	strcpy(&buf[1],tmp);
	
	va_start(ap,mesg);
	vsendit(fd,buf,ap);
	va_end(ap);

	free(tmp);
	free(buf);

	if ( close(fd)!=0 ) error(errno,"close");

	va_start(ap,mesg);
	verror(errnum,mesg,ap);
}

int receive(int fd)
{
	char buffer[SOCKETBUFFERSIZE];
	int nread;
	int i;
	
	if ( (nread = read(fd, buffer, SOCKETBUFFERSIZE)) == -1 ) {
		error(errno,"reading from socket");
	}

	/* Check for end of file */
	if ( nread==0 ) {
		return 0;
	}
	
	strcpy(lastmesg,buffer);
	while ( nread>0 && iscntrl(lastmesg[nread-1]) ) lastmesg[--nread] = 0;
	logmsg(LOG_DEBUG, "recv: %s", lastmesg);

	if ( lastmesg[0]!='+' ) {
		close(fd);
		i = 0;
		if ( lastmesg[i]=='-' ) i++;
		if ( strncmp(&lastmesg[i],ERRMATCH,strlen(ERRMATCH))==0 ) {
			i += strlen(ERRMATCH);
		}
		error(0,&lastmesg[i]);
	}

	/* Remove the first character from the message (if '+' or '-') */
	if ( lastmesg[0]=='+' || lastmesg[0]=='-' ) {
		for(i=0; i<nread; i++) lastmesg[i] = lastmesg[i+1];
		nread--;
	}

	return nread;
}

char *allocstr(char *mesg, ...)
{
	va_list ap;
	char *str;
	char tmp[2];
	int len, n;

	va_start(ap,mesg);
	len = vsnprintf(tmp,1,mesg,ap);
	va_end(ap);
	
	if ( (str = (char *) malloc(len+1))==NULL ) error(errno,"allocating string");

	va_start(ap,mesg);
	n = vsnprintf(str,len+1,mesg,ap);
	va_end(ap);

	if ( n==-1 || n>len ) error(0,"cannot write all of string");

	return str;
}

string stringtolower(string str)
{
	unsigned int i;

	for(i=0; i<str.length(); i++) str[i] = tolower(str[i]);

	return str;
}

int execute(char *cmd, char **args, int nargs, int stdio_fd[3], int err2out)
{
	pid_t pid, child_pid;
	int redirect;
	int status;
	int inpipe[2];
	int outpipe[2];
	int errpipe[2];
	char *argv[MAXARGS+2];
	int i;
	
	if ( nargs>MAXARGS ) return -2;

	redirect = ( stdio_fd[0] || stdio_fd[1] || stdio_fd[2] );
	
	/* Build the complete argument list for execvp */
	argv[0] = cmd;
	for(i=0; i<nargs; i++) argv[i+1] = args[i];
	argv[nargs+1] = NULL;

	/* Open pipes for IO redirection */
	if ( err2out ) stdio_fd[2] = 0;
	
	if ( stdio_fd[0] && pipe(inpipe )!=0 ) return -1;
	if ( stdio_fd[1] && pipe(outpipe)!=0 ) return -1;
	if ( stdio_fd[2] && pipe(errpipe)!=0 ) return -1;

	switch ( child_pid = fork() ) {
	case -1: /* error */
		return -1;
		
	case  0: /* child process */
		/* Connect pipes to command stdin/stdout and close unneeded fd's */
		if ( stdio_fd[0] ) {
			if ( dup2(inpipe[POUT],STDIN_FILENO)<0 ) return -1;
			if ( close(inpipe[PIN])!=0 ) return -1;
		}
		if ( stdio_fd[1] ) {
			if ( dup2(outpipe[PIN],STDOUT_FILENO)<0 ) return -1;
			if ( close(outpipe[POUT])!=0 ) return -1;
		}
		if ( stdio_fd[2] ) {
			if ( dup2(errpipe[PIN],STDERR_FILENO)<0 ) return -1;
			if ( close(errpipe[POUT])!=0 ) return -1;
		}
		if ( err2out && dup2(STDOUT_FILENO,STDERR_FILENO)<0 ) return -1;
		
		/* Replace child with command */
		execvp(cmd,argv);
		return -1;
		
	default: /* parent process */
		/* Set and close file descriptors */
		if ( stdio_fd[0] ) {
			stdio_fd[0] = inpipe[PIN];
			if ( close(inpipe[POUT])!=0 ) return -1;
		}
		if ( stdio_fd[1] ) {
			stdio_fd[1] = outpipe[POUT];
			if ( close(outpipe[PIN])!=0 ) return -1;
		}
		if ( stdio_fd[2] ) {
			stdio_fd[2] = errpipe[POUT];
			if ( close(errpipe[PIN])!=0 ) return -1;
		}

		/* Return if some IO is redirected to be able to read/write to child */
		if ( redirect ) return child_pid;
	
		/* Wait for the child command to finish */
		while ( (pid = wait(&status))!=-1 && pid!=child_pid );
		if ( pid!=child_pid ) return -1;

		/* Test whether command has finished abnormally */
		if ( ! WIFEXITED(status) ) {
			if ( WIFSIGNALED(status) ) return 128+WTERMSIG(status);
			if ( WIFSTOPPED (status) ) return 128+WSTOPSIG(status);
			return -2;
		}
		return WEXITSTATUS(status);
	}

	/* This should never be reached */
	return -2;
}
