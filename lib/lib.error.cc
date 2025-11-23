/*
 * Error handling and logging functions
 *
 * Part of the DOMjudge Programming Contest Jury System and licensed
 * under the GNU GPL. See README and COPYING for details.
 */

#include "config.h"

#include "lib.error.hpp"

#include <cstdlib>
#include <cstring>
#include <cstdarg>
#include <cstdio>
#include <vector>
#include <string>
#include <iostream>
#include <fstream>
#include <unistd.h>
#include <chrono>

/* Use program name in syslogging if defined */
#ifndef PROGRAM
#define PROGRAM NULL
#endif

const int exit_failure = -1;

/* Variables defining logmessages verbosity to stderr/logfile */
int  verbose      = LOG_NOTICE;
int  loglevel     = LOG_DEBUG;

/* Variables for tracking logging facilities */
static std::ofstream stdlog;
int  syslog_open  = 0;

/* Core logging implementation that accepts a fully formatted string */
void logmsg_str(int msglevel, const std::string& mesg)
{
	std::string buffer;
	char *str, *endptr;
	int syslog_fac;

	/* Try to open logfile if it is defined */
#ifdef LOGFILE
	if (!stdlog.is_open()) {
		stdlog.open(LOGFILE, std::ios::out | std::ios::app);
	}
#endif

	/* Try to open syslog if it is defined */
	if ( ! syslog_open && (str=getenv("DJ_SYSLOG"))!=NULL ) {
		syslog_fac = strtol(str,&endptr,10);
		if ( *endptr==0 ) {
			openlog(PROGRAM, LOG_NDELAY | LOG_PID, syslog_fac);
			syslog_open = 1;
		}
	}

	auto now = std::chrono::system_clock::now();
	auto millis = std::chrono::duration_cast<std::chrono::milliseconds>(now.time_since_epoch()) % 1000;
	std::string timestring = std::format("{:%b %d %H:%M:%S}.{:03d}",
			std::chrono::floor<std::chrono::seconds>(now), millis.count());

	// Construct the format string: "[time] progname[pid]: message\n"
	buffer += "[" + timestring + "] ";
	buffer += progname;
	buffer += "[" + std::to_string(getpid()) + "]: ";
	buffer += mesg;

	if ( msglevel<=verbose ) {
		std::cerr << buffer << std::endl;
	}
	if ( msglevel<=loglevel && stdlog.is_open() ) {
		stdlog << buffer << std::endl;
	}

	if ( msglevel<=loglevel && syslog_open ) {
		syslog(msglevel, "%s", mesg.c_str());
	}
}
