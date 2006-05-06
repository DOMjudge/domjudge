/*
 * Common socket send/receive code for submit and submitdaemon programs.
 *
 * $Id$
 */

#include "submitcommon.h"
#include <stdio.h>
#include <stdlib.h>
#include <ctype.h>
#include <unistd.h>
#include <string.h>
#include <sys/wait.h>

char lastmesg[SOCKETBUFFERSIZE];

void vsendit(int fd, char *mesg, va_list ap)
{
	char buffer[SOCKETBUFFERSIZE];
	ssize_t nwrite;
	
	vsnprintf(buffer,SOCKETBUFFERSIZE-2,mesg,ap);
	
	logmsg(LOG_DEBUG,"send: %s",buffer);
	
	strcat(buffer,"\015\012");

	nwrite = write(fd,buffer,strlen(buffer));
	if ( nwrite<0 ) error(errno,"writing to socket");
	if ( nwrite<(int)strlen(buffer) ) error(0,"message sent incomplete");
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

	tmp = errorstring(ERRSTR,errnum,mesg);

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

void sendwarning(int fd, int errnum, char *mesg, ...)
{
	va_list ap;
	char *buf, *tmp;

	tmp = errorstring(WARNSTR,errnum,mesg);

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
	vwarning(errnum,mesg,ap);
}

int receive(int fd)
{
	char buffer[SOCKETBUFFERSIZE];
	ssize_t nread;
	int i;
	
	if ( (nread = read(fd, buffer, SOCKETBUFFERSIZE)) == -1 ) {
		error(errno,"reading from socket");
	}

	/* Check for end of file */
	if ( nread==0 ) return 0;
	
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

std::string stringtolower(std::string str)
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
