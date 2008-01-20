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
 * turn calls errorstring to generate the actual error message;
 */

/* Check for GNU libc version of strerror_r, which doesn't comply with
 * POSIX standards. From man-page: 
 *   
 *   char *strerror_r(int errnum, char *buf, size_t n);
 * 
 * is a GNU extension used by glibc (since 2.0), and must be regarded
 * as obsolete in view of SUSv3.  The GNU version may, but need not,
 * use the user-supplied buffer. If it does, the result may be
 * truncated in case the supplied buffer is too small. The result is
 * always NUL-terminated.
 */
#if defined(__GLIBC__) && __GLIBC__==2 && __GLIBC_MINOR__<=3
#define GLIB_STRERROR 1
#else
/* In glibc >= 2.4 we have the POSIX version of 'strerror_r' when defining: */
#define _XOPEN_SOURCE 600
#endif

#include "lib.error.h"

#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <time.h>

/* For SYSLOG config variable */
#include "../etc/config.h"

/* Use program name in syslogging if defined */
#ifndef PROGRAM
#define PROGRAM NULL
#endif

const int exit_failure = -1;

/* Variables defining logmessages verbosity to stderr/logfile */
int  verbose      = LOG_NOTICE;
int  loglevel     = LOG_DEBUG;

/* Variables for tracking logging facilities */
FILE *stdlog      = NULL;
int  syslog_open  = 0;

/* Main function that contains logging code */
void vlogmsg(int msglevel, const char *mesg, va_list ap)
{
    time_t currtime;
    char timestring[128];
	char *buffer;
	int mesglen = (mesg==NULL ? 0 : strlen(mesg));
	int bufferlen;
	va_list aq;
	
	/* Try to open logfile if it is defined */
#ifdef LOGFILE
	if ( stdlog==NULL ) stdlog = fopen(LOGFILE,"a");
#endif

	/* Try to open syslog if it is defined */
#ifdef SYSLOG
	if ( ! syslog_open ) {
		openlog(PROGRAM, LOG_NDELAY | LOG_PID, SYSLOG);
		syslog_open = 1;
	}
#endif

	currtime = time(NULL);
	strftime(timestring, sizeof(timestring), "%b %d %H:%M:%S", localtime(&currtime));

	bufferlen = strlen(timestring)+strlen(progname)+mesglen+20;
	buffer = (char *)malloc(bufferlen);
	if ( buffer==NULL ) abort();

	snprintf(buffer, bufferlen, "[%s] %s[%d]: %s\n",
	         timestring, progname, getpid(), mesg);
	
	if ( msglevel<=verbose ) {
		va_copy(aq, ap);
		vfprintf(stderr, buffer, aq);
		fflush(stderr);
		va_end(aq);
	}
	if ( msglevel<=loglevel && stdlog!=NULL ) {
		va_copy(aq, ap);
		vfprintf(stdlog, buffer, aq);
		fflush(stdlog);
		va_end(aq);
	}

	free(buffer);

#ifdef SYSLOG
	if ( msglevel<=loglevel ) {
		buffer = vallocstr(mesg, ap);
		syslog(msglevel, buffer);
		free(buffer);
	}
#endif
}

/* Argument-list wrapper function around vlogmsg */
void logmsg(int msglevel, const char *mesg, ...)
{
	va_list ap;
	va_start(ap, mesg);

	vlogmsg(msglevel, mesg, ap);

	va_end(ap);
}

/* Function to generate error/warning string */
char *errorstring(const char *type, int errnum, const char *mesg)
{
	int buffersize;
	char *buffer;
	char *endptr; /* pointer to current end of buffer */
#ifdef GLIB_STRERROR
	char *tmpstr;
	int tmplen;
#endif

	if ( type==NULL ) {
		type = strdup(ERRSTR);
		if ( type==NULL ) abort();
	}

	/* 256 > maxlength strerror() */
	buffersize = strlen(type) + (mesg==NULL ? 0 : strlen(mesg)) + 256;

	endptr = buffer = (char *) malloc(buffersize);
	if ( buffer==NULL ) abort();

	sprintf(buffer,type);
	endptr = strchr(endptr,0);
	
	if ( mesg!=NULL ) {
		snprintf(endptr, buffersize-strlen(buffer), ": %s", mesg);
		endptr = strchr(endptr,0);
	}		
	if ( errnum!=0 ) {
		snprintf(endptr, buffersize-strlen(buffer), ": ");
		endptr = strchr(endptr,0);
#ifdef GLIB_STRERROR
		tmplen = buffersize-strlen(buffer);
		tmpstr = strerror_r(errnum, endptr, tmplen);
		strncat(endptr, tmpstr, tmplen);
#else
		strerror_r(errnum, endptr, buffersize-strlen(buffer));
#endif
		endptr = strchr(endptr,0);
	}
	if ( mesg==NULL && errnum==0 ) {
		sprintf(endptr,": unknown error");
		endptr = strchr(endptr,0);
	}

	return buffer;
}

/* Function to generate and write error logmessage (using vlogmsg) */
void vlogerror(int errnum, const char *mesg, va_list ap)
{
	char *buffer;

	buffer = errorstring(ERRSTR, errnum, mesg);

	vlogmsg(LOG_ERR, buffer, ap);

	free(buffer);
}

/* Argument-list wrapper function around vlogerror */
void logerror(int errnum, const char *mesg, ...)
{
	va_list ap;
	va_start(ap, mesg);

	vlogerror(errnum, mesg, ap);

	va_end(ap);
}

/* Logs an error message and exit with non-zero exitcode */
void verror(int errnum, const char *mesg, va_list ap)
{
	vlogerror(errnum, mesg, ap);

	exit(exit_failure);
}

/* Argument-list wrapper function around verror */
void error(int errnum, const char *mesg, ...)
{
	va_list ap;
	va_start(ap, mesg);

	verror(errnum, mesg, ap);

	va_end(ap);
}

/* Logs a warning message */
void vwarning(int errnum, const char *mesg, va_list ap)
{
	char *buffer;

	buffer = errorstring(WARNSTR, errnum, mesg);

	vlogmsg(LOG_WARNING, buffer, ap);

	free(buffer);
}

/* Argument-list wrapper function around vwarning */
void warning(int errnum, const char *mesg, ...)
{
	va_list ap;
	va_start(ap, mesg);

	vwarning(errnum, mesg, ap);

	va_end(ap);
}

/* Allocates a string with variable arguments */
char *vallocstr(const char *mesg, va_list ap)
{
	char *str;
	char tmp[2];
	int len, n;
	va_list aq;

	va_copy(aq,ap);
	len = vsnprintf(tmp,1,mesg,aq);
	va_end(aq);

	if ( (str = (char *) malloc(len+1))==NULL ) error(errno,"allocating string");

	va_copy(aq,ap);
	n = vsnprintf(str,len+1,mesg,aq);
	va_end(aq);

	if ( n==-1 || n>len ) error(0,"cannot write all of string");

	return str;
}

/* Argument-list wrapper function around vallocstr */
char *allocstr(const char *mesg, ...)
{
	va_list ap;
	char *str;

	va_start(ap,mesg);
	str = vallocstr(mesg,ap);
	va_end(ap);
	
	return str;
}
