/*
 * Common socket send/receive code for submit and submitdaemon programs.
 *
 * $Id$
 */

#include <stdio.h>
#include <ctype.h>
#include <string.h>

#define SOCKETBUFFERSIZE 256

#define ERRSTR "error: "

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

/*** 
 *  Receive mesg from socket and log it.
 */
int receive(int fd)
{
	char buffer[SOCKETBUFFERSIZE];
	int nread;
	int i;
	
	if ( (nread = read(fd, buffer, SOCKETBUFFERSIZE)) == -1 ) {
		error(errno,"reading from socket");
	}

	strcpy(lastmsg,buffer);
	while ( nread>0 && iscntrl(lastmesg[nread-1]) ) lastmesg[--nread] = 0;
	logmsg(LOG_DEBUG, "recv: %s", lastmesg);

	if ( lastmesg[0]!='+' ) {
		close(fd);
		i = 0;
		if ( lastmesg[i]=='-' ) i++;
		if ( strncmp(&lastmesg[i],ERRSTR,strlen(ERRSTR))==0 ) {
			i += strlen(ERRSTR);
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
