/*
 * Common socket send/receive code for submit and submitdaemon programs.
 *
 * $Id$
 */

#include <stdio.h>
#include <ctype.h>
#include <string.h>

/* Logging and error functions */
#include "../lib/lib.error.h"

#define SOCKETBUFFERSIZE 256

/* Buffer where the last received message is stored */
char lastmesg[SOCKETBUFFERSIZE];

/* Send a message over a socket and log it.
 *
 * Arguments:
 * int fd      filedescriptor of the socket
 * char *mesg  message to write, may include printf format characters '%'
 * ...         optional arguments for format characters
 */
void sendit(int, char *, ...);

/* Receive a message over a socket and log it.
 * Message is put in 'lastmesg' and number of characters read is returned.
 *
 * Arguments:
 * int fd      filedescriptor of the socket
 */
int  receive(int);

/* Create a c-string by allocating memory for it and writing to it,
 * using printf type format characters.
 *
 * Arguments:
 * char *mesg  message to write, may include printf format characters '%'
 * ...         optional arguments for format characters
 *
 * Returns a pointer to the allocated string
 */
char *allocstr(char *, ...);



void sendit(int fd, char *mesg, ...)
{
	char buffer[SOCKETBUFFERSIZE];
	va_list ap;

	va_start(ap, mesg);
	vsnprintf(buffer, SOCKETBUFFERSIZE-2, mesg, ap);
	va_end(ap);
	
	logmsg(LOG_DEBUG, "send: %s", buffer);
	strcat(buffer, "\015\012");
	
	if ( write(fd, buffer, strlen(buffer)) == -1 ) {
		error(errno,"writing to socket");
	}
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
