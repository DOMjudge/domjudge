/*
 * Error handling and logging functions
 *
 * $Id$
 */

#ifndef _LIB_ERROR_
#define _LIB_ERROR_

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <stdarg.h>
#include <unistd.h>
#include <syslog.h>
#include <time.h>
#include <errno.h>

#define ERRSTR   "error"
#define ERRMATCH ERRSTR": "

const int exit_failure = -1;

/* Import from the main program for logging purposes */
extern char *progname;

/* Variables defining logmessages verbosity to stderr/logfile */
int  verbose      = LOG_NOTICE;
int  loglevel     = LOG_DEBUG;
FILE *stdlog      = NULL;

/* Logging function (vlogmsg uses va_list instead of argument list):
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

/* Error string generating function:
 * Returns a pointer to a dynamically allocated string containing the
 * complete error message.
 *
 * Arguments:
 * int errnum      'errno' value to use for error string output, set 0 to skip
 * char *mesg      message, may include printf output format characters '%'
 * va_list         optional arguments for format characters
 *
 * Returns a char pointer to the allocated string.
 */
char *errorstring(int, char *, va_list);

/* Error and warning functions (v.. uses va_list instead of argument list):
 * Logs an error message including error string from 'errno'.
 *   logerror   only logs the error message
 *   error      log the message and exits with exit_failure
 *   warning    log the message and generates extra warning signals
 *
 * Arguments:
 * int errnum      'errno' value to use for error string output, set 0 to skip
 * char *mesg      message, may include printf output format characters '%'
 * ... or va_list  optional arguments for format characters
 */
void logerror (int, char *, ...);
void error    (int, char *, ...);
void warning  (int, char *, ...);
void vlogerror(int, char *, va_list);
void verror   (int, char *, va_list);
void vwarning (int, char *, va_list);


/* ========================= IMPLEMENTATION ============================== */

/* Implementation details:
 *
 * All argument-list based functions call their va_list (v..) counterparts
 * to avoid doubled code. Furthermore, all functions use 'vlogmsg' to do the
 * actual writing of the logmessage and all error/warning functions use
 * 'vlogerror' to generate the error-logmessage from the input. vlogerror in
 * turn calls verrorstr to generate the actual error message;
 */

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
char *errorstring(int errnum, char *mesg, va_list ap)
{
	int mesglen = (mesg==NULL ? 0 : strlen(mesg));
	char *buffer;
	char *endptr; /* pointer to current end of buffer */
	
	endptr = buffer = (char *) malloc(mesglen+256);
	if ( buffer==NULL ) abort();

	sprintf(buffer,ERRSTR);
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

	return buffer;
}

/* Function to generate and write error logmessage (using vlogmsg) */
void vlogerror(int errnum, char *mesg, va_list ap)
{
	char *buffer;

	buffer = errorstring(errnum, mesg, ap);
	
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

#endif /* _LIB_ERROR_ */
