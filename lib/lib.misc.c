/*
 * Miscellaneous common functions for C/C++ programs.
 *
 * $Id$
 */

#include "lib.misc.h"
#include "lib.error.h"

#include <stdlib.h>
#include <unistd.h>

/* Array indices for input/output file descriptors as used by pipe() */
#define PIPE_IN  1
#define PIPE_OUT 0

char *allocstr(char *mesg, ...)
{
	va_list ap;
	char *str;
	char tmp[2];
	int len, n;

	va_start(ap, mesg);
	len = vsnprintf(tmp, 1, mesg, ap);
	va_end(ap);
	
	if ( (str = (char *) malloc(len + 1)) == NULL )
		error(errno, "allocating string");

	va_start(ap, mesg);
	n = vsnprintf(str, len + 1, mesg, ap);
	va_end(ap);

	if ( n == -1 || n > len )
		error(0, "cannot write all of string");

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
	for ( i = 0; i < nargs; i++ )
		argv[i + 1] = args[i];
	argv[nargs + 1] = NULL;

	/* Open pipes for IO redirection */
	if ( err2out )
		stdio_fd[2] = 0;
	
	if ( stdio_fd[0] && pipe(inpipe ) != 0 )
		return -1;
	if ( stdio_fd[1] && pipe(outpipe) != 0 )
		return -1;
	if ( stdio_fd[2] && pipe(errpipe) != 0 )
		return -1;

	switch ( child_pid = fork() ) {
	case -1: /* error */
		return -1;

	case  0: /* child process */
		/* Connect pipes to command stdin/stdout and close unneeded fd's */
		if ( stdio_fd[0] ) {
			if ( dup2(inpipe[PIPE_OUT], STDIN_FILENO) < 0 )
				return -1;
			if ( close(inpipe[PIPE_IN]) != 0 )
				return -1;
		}
		if ( stdio_fd[1] ) {
			if ( dup2(outpipe[PIPE_IN], STDOUT_FILENO) < 0 )
				return -1;
			if ( close(outpipe[PIPE_OUT]) != 0 )
				return -1;
		}
		if ( stdio_fd[2] ) {
			if ( dup2(errpipe[PIPE_IN], STDERR_FILENO) < 0 )
				return -1;
			if ( close(errpipe[PIPE_OUT]) != 0 )
				return -1;
		}
		if ( err2out && dup2(STDOUT_FILENO, STDERR_FILENO) < 0 )
			return -1;
		
		/* Replace child with command */
		execvp(cmd, argv);
		return -1;
		
	default: /* parent process */
		/* Set and close file descriptors */
		if ( stdio_fd[0] ) {
			stdio_fd[0] = inpipe[PIPE_IN];
			if ( close(inpipe[PIPE_OUT]) != 0 )
				return -1;
		}
		if ( stdio_fd[1] ) {
			stdio_fd[1] = outpipe[PIPE_OUT];
			if ( close(outpipe[PIPE_IN]) != 0 )
				return -1;
		}
		if ( stdio_fd[2] ) {
			stdio_fd[2] = errpipe[PIPE_OUT];
			if ( close(errpipe[PIPE_IN]) != 0 )
				return -1;
		}

		/* Return if some IO is redirected to be able to read/write to child */
		if ( redirect )
			return child_pid;
	
		/* Wait for the child command to finish */
		while ( (pid = wait(&status)) != -1 && pid != child_pid );
		if ( pid!=child_pid )
			return -1;

		/* Test whether command has finished abnormally */
		if ( ! WIFEXITED(status) ) {
			if ( WIFSIGNALED(status) )
				return 128 + WTERMSIG(status);
			if ( WIFSTOPPED (status) )
				return 128 + WSTOPSIG(status);
			return -2;
		}
		return WEXITSTATUS(status);
	}

	/* This should never be reached */
	return -2;
}

