/*
 * Error handling and logging functions
 *
 * $Id$
 */

/* Implementation details:
 *
 * All argument-list based functions call their va_list (v..) counterparts
 * to avoid doubled code. Furthermore, all functions use 'vlogmsg' to do the
 * actual writing of the logmessage and all error/warning functions use
 * 'vlogerror' to generate the error-logmessage from the input. vlogerror in
 * turn calls verrorstr to generate the actual error message;
 */

#include "lib.error.h"
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <stdarg.h>
#include <unistd.h>
#include <time.h>

/* Variables defining logmessages verbosity to stderr/logfile */
int  verbose      = LOG_NOTICE;
int  loglevel     = LOG_DEBUG;
FILE *stdlog      = NULL;

/* Main function that contains logging code */
void vlogmsg(int msglevel, char *mesg, va_list ap)
{
    time_t currtime;
    char timestring[128];
	char *buffer;
	int mesglen = (mesg==NULL ? 0 : strlen(mesg));
	int bufferlen;

	/* Try to open logfile if it is defined */
#ifdef LOGFILE
	if ( stdlog==NULL ) stdlog = fopen(LOGFILE,"a");
#endif
	
	currtime  = time(NULL);
	strftime(timestring, sizeof(timestring), "%b %d %H:%M:%S", localtime(&currtime));

	bufferlen = strlen(timestring)+strlen(progname)+mesglen+20;
	buffer = (char *)malloc(bufferlen);
	if ( buffer==NULL ) abort();

	snprintf(buffer, bufferlen, "[%s] %s[%d]: %s\n",
	         timestring, progname, getpid(), mesg);
	
	if ( msglevel<=verbose  ) { vfprintf(stderr, buffer, ap); fflush(stderr); }
	if ( msglevel<=loglevel &&
	     stdlog!=NULL       ) { vfprintf(stdlog, buffer, ap); fflush(stdlog); }

	free(buffer);
}

/* Argument-list wrapper function around vlogmsg */
void logmsg(int msglevel, char *mesg, ...)
{
	va_list ap;
	va_start(ap, mesg);

	vlogmsg(msglevel, mesg, ap);

	va_end(ap);
}

/* Function to generate error string */
char *errorstring(int errnum, char *mesg)
{
	int mesglen = (mesg==NULL ? 0 : strlen(mesg));
	int buffersize = mesglen + 256;
	char *buffer;
	char *endptr; /* pointer to current end of buffer */
	
	endptr = buffer = (char *) malloc(buffersize);
	if ( buffer==NULL ) abort();

	sprintf(buffer,ERRSTR);
	endptr = strchr(buffer,0);
	
	if ( mesg!=NULL ) {
		snprintf(endptr, buffersize-strlen(buffer), ": %s", mesg);
		endptr = strchr(endptr,0);
	}		
	if ( errnum!=0 ) {
		snprintf(endptr, buffersize-strlen(buffer), ": %s",strerror(errnum));
		endptr = strchr(endptr,0);
	}
	if ( mesg==NULL && errnum==0 ) {
		sprintf(endptr,": unknown error");
	}

	return buffer;
}

/* Function to generate and write error logmessage (using vlogmsg) */
void vlogerror(int errnum, char *mesg, va_list ap)
{
	char *buffer;

	buffer = errorstring(errnum, mesg);

	vlogmsg(LOG_ERR, buffer, ap);

	free(buffer);
}

/* Argument-list wrapper function around vlogerror */
void logerror(int errnum, char *mesg, ...)
{
	va_list ap;
	va_start(ap, mesg);

	vlogerror(errnum, mesg, ap);

	va_end(ap);
}

/* Logs an error message and exit with non-zero exitcode */
void verror(int errnum, char *mesg, va_list ap)
{
	vlogerror(errnum, mesg, ap);

	exit(exit_failure);
}

/* Argument-list wrapper function around verror */
void error(int errnum, char *mesg, ...)
{
	va_list ap;
	va_start(ap, mesg);

	verror(errnum, mesg, ap);
}

/* Logs an error message and generate some extra warning signals */
void vwarning(int errnum, char *mesg, va_list ap)
{
	vlogerror(errnum, mesg, ap);
}

/* Argument-list wrapper function around vwarning */
void warning(int errnum, char *mesg, ...)
{
	va_list ap;
	va_start(ap, mesg);

	vwarning(errnum, mesg, ap);

	va_end(ap);
}
