/**
 *  Error handling and logging functions
 *
 *  $Id$
 */

#ifndef _LIB_ERROR_
#define _LIB_ERROR_

#include <stdio.h>
#include <string.h>
#include <stdarg.h>
#include <syslog.h>
#include <time.h>
#include <errno.h>

const int exit_failure = -1;

/* Import from the main program */
extern char *progname;

/* Variables defining logmessages verbosity to stderr/logfile */
int  verbose      = LOG_NOTICE;
int  loglevel     = LOG_DEBUG;
char *logfile     = NULL;
FILE *stdlog      = NULL;

/* Argument-list and va_list versions of logging function:
 * Logs a message to stderr and/or logfile, including date and program name,
 * depending on the loglevel treshold values.
 *
 * Arguments:
 * int loglevel    syslog loglevel of this log-message
 * char *mesg      message, may include printf output format characters '%'
 * ... or va_list  optional arguments for format characters
 */
void logmsg (int, char *, ...);
void vlogmsg(int, char *, va_list);

/* Error and warning functions:
 * Logs an error message including error string from 'errno' and error
 * then exits with exit_failure, warning returns.
 *
 * Arguments:
 * int errnum    'errno' value to use for error string output, set 0 to skip
 * char *mesg    message, may include printf output format characters '%'
 * ...           optional arguments for format characters
 */
void error  (int, char *, ...);
void warning(int, char *, ...);


void vlogmsg(int msglevel, char *mesg, va_list ap)
{
    time_t     currtime;
	struct tm *datetime;
    char timestring[128];
	char *buffer;
	int mesglen = (mesg==NULL ? 0 : strlen(mesg));
	int bufferlen;
    
	if(stdlog==NULL && logfile!=NULL) {
		// TODO open stdlog
	}
	
    currtime  = time(NULL);
	datetime = localtime(&currtime);
    strftime(timestring, sizeof(timestring), "%b %d %T", datetime);

	bufferlen = strlen(timestring)+strlen(progname)+mesglen+20;
	buffer = (char *)malloc(bufferlen);

	snprintf(buffer, bufferlen, "[%s] %s[%d]: %s\n",
	         timestring, progname, getpid(), mesg);
	
    if ( msglevel<=verbose  ) { vfprintf(stderr, buffer, ap); fflush(stderr); }
	if ( msglevel<=loglevel &&
	     stdlog!=NULL       ) { vfprintf(stdlog, buffer, ap); fflush(stdlog); }

	free(buffer);
}

void logmsg(int msglevel, char *mesg, ...)
{
	va_list ap;
	va_start(ap, mesg);

	vlogmsg(msglevel, mesg, ap);

	va_end(ap);
}

void error(int errnum, char *mesg, ...)
{
	int mesglen = (mesg==NULL ? 0 : strlen(mesg));
	char *buffer, *endptr;
	va_list ap;
	
	va_start(ap, mesg);
	
	buffer = (char *) malloc(mesglen+256);

	sprintf(buffer,"error");
	endptr = strchr(buffer,0);
	
	if ( mesg!=NULL ) {
		snprintf(endptr, sizeof(buffer)-strlen(buffer), ": %s", mesg);
		endptr = strchr(endptr,0);
	}		
	if ( errnum!=0 ) {
		snprintf(endptr, sizeof(buffer)-strlen(buffer), ": %s",strerror(errnum));
		endptr = strchr(endptr,0);
	}
	if ( mesg==NULL && errnum==0 ) {
		sprintf(endptr,": unknown error");
	}

	vlogmsg(LOG_ERR, buffer, ap);

	free(buffer);
	va_end(ap);
	exit(exit_failure);
}

void warning(int errnum, char *mesg, ...)
{
	int mesglen = (mesg==NULL ? 0 : strlen(mesg));
	char *buffer, *endptr;
	va_list ap;
	
	va_start(ap, mesg);
	
	buffer = (char *) malloc(mesglen+256);

	sprintf(buffer,"error");
	endptr = strchr(buffer,0);
	
	if ( mesg!=NULL ) {
		snprintf(endptr, sizeof(buffer)-strlen(buffer), ": %s", mesg);
		endptr = strchr(endptr,0);
	}		
	if ( errnum!=0 ) {
		snprintf(endptr, sizeof(buffer)-strlen(buffer), ": %s",strerror(errnum));
		endptr = strchr(endptr,0);
	}
	if ( mesg==NULL && errnum==0 ) {
		sprintf(endptr,": unknown error");
	}

	vlogmsg(LOG_ERR, buffer, ap);

	free(buffer);
	va_end(ap);
	exit(exit_failure);
}

#endif /* _LIB_ERROR_ */
