/*
 * Common socket send/receive code for submit and submitdaemon programs.
 *
 * $Id$
 */

#include <stdio.h>
#include <ctype.h>
#include <string.h>

#define SOCKETBUFFERSIZE 256

/* Buffer where the last received message is stored */
char lastmesg[SOCKETBUFFERSIZE];

/* Send a message over a socket and log it.
 *
 * Arguments:
 * int fd      filedescriptor of the socket
 * char *mesg  message to write  
 */
void sendit(int, char*);

/* Receive a message over a socket and log it.
 * Message is put in 'lastmesg' and number of characters read is returned.
 *
 * Arguments:
 * int fd      filedescriptor of the socket
 */
int  receive(int);

void sendit(int fd, char *mesg)
{
	char buffer[SOCKETBUFFERSIZE];

	strncpy(buffer,mesg,SOCKETBUFFERSIZE-2);

	logmsg(LOG_DEBUG, "send: %s", buffer);
	strcat(buffer,"\015\012");
	
	if ( write(fd, buffer, strlen(buffer)) == -1 ) {
		error(errno,"writing to socket");
	}
}

/*** 
 *  Receive mesg from socket and log it.
 */
int receive(int fd)
{
	int nread;
	
	if ( (nread = read(fd, lastmesg, SOCKETBUFFERSIZE)) == -1 ) {
		error(errno,"reading from socket");
	}

	while ( nread>0 && iscntrl(lastmesg[nread-1]) ) lastmesg[--nread] = 0;
	logmsg(LOG_DEBUG, "recv: %s", lastmesg);

	return nread;
}
