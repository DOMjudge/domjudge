/*
 * Common socket send/receive code for submit and submitdaemon programs.
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

#include "config.h"

#include "submitcommon.hxx"

#include "lib.error.h"

#include <stdlib.h>
#include <stdio.h>
#include <ctype.h>
#include <string.h>

char lastmesg[SOCKETBUFFERSIZE];

void vsendit(int fd, const char *mesg, va_list ap)
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

void sendit(int fd, const char *mesg, ...)
{
	va_list ap;

	va_start(ap,mesg);
	vsendit(fd,mesg,ap);
	va_end(ap);
}

void senderror(int fd, int errnum, const char *mesg, ...)
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

void sendwarning(int fd, int errnum, const char *mesg, ...)
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

	if ( (nread = read(fd, buffer, SOCKETBUFFERSIZE-2)) == -1 ) {
		error(errno,"reading from socket");
		return -1;	// never reached, but removes gcc warning
	}

	buffer[nread] = 0;

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

std::string stringtolower(std::string str)
{
	unsigned int i;

	for(i=0; i<str.length(); i++) str[i] = tolower(str[i]);

	return str;
}
