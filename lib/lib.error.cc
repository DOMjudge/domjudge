/*
 * Error handling and logging functions
 *
 * Part of the DOMjudge Programming Contest Jury System and licensed
 * under the GNU GPL. See README and COPYING for details.
 */

/* Implementation details:
 *
 * All argument-list based functions call their va_list (v..) counterparts
 * to avoid doubled code. Furthermore, all functions use 'vlogmsg' to do the
 * actual writing of the logmessage and all error/warning functions use
 * 'vlogerror' to generate the error-logmessage from the input. vlogerror in
 * turn calls errorstring to generate the actual error message;
 */

#include "config.h"

#include "lib.error.h"

#include <cstdlib>
#include <cstring>
#include <cstdarg>
#include <cstdio>
#include <ctime>
#include <vector>
#include <string>
#include <iostream>
#include <unistd.h>
#include <sys/time.h>

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

/* Escape '%' characters in a string for use in printf like functions */
static std::string escape_percent(const char *str)
{
	if (str == NULL) return "";
	std::string escaped;
	size_t len = strlen(str);
	size_t percent_count = 0;

	for (size_t i = 0; i < len; ++i) {
		if (str[i] == '%') {
			percent_count++;
		}
	}

	escaped.reserve(len + percent_count);

	for (size_t i = 0; i < len; ++i) {
		escaped += str[i];
		if (str[i] == '%') {
			escaped += '%';
		}
	}
	return escaped;
}

/* Main function that contains logging code */
void vlogmsg(int msglevel, const char *mesg, va_list ap)
{
	struct timeval currtime;
	struct tm tm_buf;
	char timestring[128];
	std::string buffer;
	va_list aq;
	char *str, *endptr;
	int syslog_fac;

	/* This should never happen when called from any of the functions below. */
	if ( mesg==NULL ) abort();

	/* Try to open logfile if it is defined */
#ifdef LOGFILE
	if ( stdlog==NULL ) stdlog = fopen(LOGFILE,"a");
#endif

	/* Try to open syslog if it is defined */
	if ( ! syslog_open && (str=getenv("DJ_SYSLOG"))!=NULL ) {
		syslog_fac = strtol(str,&endptr,10);
		if ( *endptr==0 ) {
			openlog(PROGRAM, LOG_NDELAY | LOG_PID, syslog_fac);
			syslog_open = 1;
		}
	}

	gettimeofday(&currtime, NULL);
	localtime_r(&currtime.tv_sec, &tm_buf);
	strftime(timestring, sizeof(timestring), "%b %d %H:%M:%S", &tm_buf);
	sprintf(timestring+strlen(timestring), ".%03d", (int)(currtime.tv_usec/1000));

	std::string progname_escaped = escape_percent(progname);

	// Construct the format string: "[time] progname[pid]: message\n"
	buffer = "[";
	buffer += timestring;
	buffer += "] ";
	buffer += progname_escaped;
	buffer += "[";
	buffer += std::to_string(getpid());
	buffer += "]: ";
	buffer += mesg;
	buffer += "\n";

	if ( msglevel<=verbose ) {
		va_copy(aq, ap);
		vfprintf(stderr, buffer.c_str(), aq);
		fflush(stderr);
		va_end(aq);
	}
	if ( msglevel<=loglevel && stdlog!=NULL ) {
		va_copy(aq, ap);
		vfprintf(stdlog, buffer.c_str(), aq);
		fflush(stdlog);
		va_end(aq);
	}

	if ( msglevel<=loglevel && syslog_open ) {
		char *syslog_buf = vallocstr(mesg, ap);
		syslog(msglevel, "%s", syslog_buf);
		free(syslog_buf);
	}
}

/* Argument-list wrapper function around vlogmsg */
void logmsg(int msglevel, const char *mesg, ...)
{
	va_list ap;
	va_start(ap, mesg);

	vlogmsg(msglevel, mesg, ap);

	va_end(ap);
}

/* Function to generate error/warning string:
   - allocates memory for string (needs freeing later)
   - generates message of the form:
       errtype . ": " . mesg . ": " . errdescr
     where 'errtype' can be "WARNING" / "ERROR"
	 'mesg' is a program generated message (or NULL)
	 'errnum' is the last system call's errno (or zero)
*/
char *errorstring(const char *type, int errnum, const char *mesg)
{
	std::string errtype;
	std::string errdescr;
	std::string buffer;

	/* Set errtype to given string or default to 'ERROR' */
	if ( type==NULL ) {
		errtype = ERRSTR;
	} else {
		errtype = type;
	}

	if ( errnum != 0 ) {
		errdescr = strerror(errno);
	} else if ( mesg == NULL ) {
		errdescr = "unknown error";
	}

	buffer = errtype + ": ";

	if ( mesg != NULL )     buffer += mesg;

	if ( mesg != NULL &&
	     !errdescr.empty() ) buffer += ": ";

	if ( !errdescr.empty() ) buffer += errdescr;

	char *res = strdup(buffer.c_str());
	if ( res==NULL ) abort();

	return res;
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
