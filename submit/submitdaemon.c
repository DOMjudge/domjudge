/*
   submitdaemon -- server for the submit program.
   Copyright (C) 2004 Peter van de Werken, Jaap Eldering.

   Based on submitdaemon.pl by Eelco Dolstra.
   
   $Id$

   This program is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; either version 2, or (at your option)
   any later version.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with this program; if not, write to the Free Software Foundation,
   Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.

 */

#include <stdarg.h>
#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <errno.h>
#include <syslog.h>
#include <string.h>
#include <sys/wait.h>
#include <sys/types.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>
#include <signal.h>
#include <getopt.h>
#include <pwd.h>
#include <libgen.h>

/* Some C++ includes for easy string handling */
using namespace std;
#include <iostream>
#include <sstream>
#include <string>

/* Some system/site specific config */
#include "../etc/config.h"

/* Logging and error functions */
#include "../lib/lib.error.h"

/* Common send/receive functions */
#include "submitcommon.h"

#define PROGRAM "submitdaemon"
#define VERSION "0.1"
#define AUTHORS "Peter van de Werken & Jaap Eldering"

#define BACKLOG 32      // how many pending connections queue will hold

extern int errno;

/* Variables defining logmessages verbosity to stderr/logfile */
#define LOGFILE LOGDIR"/submit.log"

extern int verbose;
extern int loglevel;

char *progname;

int port = SUBMITPORT;

int show_help;
int show_version;

struct option const long_opts[] = {
	{"port",    required_argument, NULL,         'P'},
	{"verbose", required_argument, NULL,         'v'},
	{"help",    no_argument,       &show_help,    1 },
	{"version", no_argument,       &show_version, 1 },
	{ NULL,     0,                 NULL,          0 }
};

int server_fd, client_fd;       /* server/client socket filedescriptors */
struct sockaddr_in server_addr; /* server address information */
struct sockaddr_in client_addr; /* client address information */
socklen_t sin_size;

void version()
{
	printf("%s -- version %s\n",PROGRAM,VERSION);
	printf("Written by %s\n\n",AUTHORS);
	printf("%s comes with ABSOLUTELY NO WARRANTY.  This is free software, and you\n",PROGRAM);
	printf("are welcome to redistribute it under certain conditions.  See the GNU\n");
	printf("General Public Licence for details.\n");
	exit(0);
}

void usage()
{
	printf("Usage: %s [OPTION]...\n",progname);
	printf("Start the submitserver.\n");
	printf("\n");
	printf("  -P, --port=PORT       set tcp port to listen on to PORT\n");
	printf("  -v, --verbose=LEVEL   set verbosity to LEVEL (syslog levels)\n");
	printf("      --help            display this help and exit\n");
	printf("      --version         output version information and exit\n");
	printf("\n");
	exit(0);
}

void create_server();
int  handle_client();
void sigchld_handler(int);

int main(int argc, char **argv)
{
	struct sigaction sigchildaction;
	int child_pid;
	int c;
	char *ptr;
	
	/* Set logging levels & open logfile */
	verbose  = LOG_DEBUG;
	loglevel = LOG_DEBUG;
	stdlog   = fopen(LOGFILE,"a");
	if ( stdlog==NULL ) error(errno,"cannot open logfile `%s'",LOGFILE);

	progname = argv[0];

	/* Parse command-line options */
	show_help = show_version = 0;
	opterr = 0;
	while ( (c = getopt_long(argc,argv,"P:v:q",long_opts,NULL))!=-1 ) {
		switch ( c ) {
		case 0:   /* long-only option */
			break;
		case 'P': /* port option */
			port = strtol(optarg,&ptr,10);
			if ( ptr!=0 || port<0 || port>65535 ) {
				error(0,"invalid tcp port specified: `%s'",optarg);
			}
			break;
		case 'v': /* verbose option */
			verbose = strtol(optarg,&ptr,10);
			if ( ptr!=0 || verbose<0 ) {
				error(0,"invalid verbosity specified: `%s'",optarg);
			}
			break;
		case ':': /* getopt error */
		case '?':
			error(0,"unknown option or missing argument `%c'",optopt);
			break;
		default:
			error(0,"getopt returned character code `%c' ??",c);
		}
	}

	if ( show_help ) usage();
	if ( show_version ) version();
	
	if ( argc>optind ) error(0,"non-option arguments given");

	logmsg(LOG_NOTICE,"server started");

	create_server();
	logmsg(LOG_INFO,"listening on port %d/tcp", port);
    
    /* Setup the child signal handler */
	sigchildaction.sa_handler = sigchld_handler;
	sigemptyset(&sigchildaction.sa_mask);
	sigchildaction.sa_flags = SA_RESTART;
	if ( sigaction(SIGCHLD,&sigchildaction,NULL)!=0 ) {
		error(errno,"setting child signal handler");
	}
	logmsg(LOG_DEBUG,"child signal handler installed");
    
    /* main accept() loop */
    while ( true ) {
        sin_size = sizeof(struct sockaddr_in);
		
		if ( (client_fd = accept(server_fd, (struct sockaddr *) &client_addr,
		                         &sin_size)) == -1 ) {
			warning(errno,"accepting incoming connection");
			continue;
		}
        
		logmsg(LOG_INFO,"incoming connection, spawning child");
        
		switch ( child_pid = fork() ) {
		case -1: /* error */
			error(errno,"cannot fork");
		
		case  0: /* child thread */
			close(server_fd); /* child doesn't need the listener */
			logmsg(LOG_NOTICE,"connection from %s",inet_ntoa(client_addr.sin_addr));

			handle_client();

			logmsg(LOG_INFO,"child exiting");
			exit(0);

		default: /* parent thread */
			close(client_fd); /* parent doesn't need the client_fd */
			logmsg(LOG_DEBUG,"spawned child, pid=%d", child_pid);
		}

	}

	return 0; /* This should never be reached */
}

/***
 *  Convert a C++ string to lowercase
 */
string stringtolower(string str)
{
	unsigned int i;

	for(i=0; i<str.length(); i++) str[i] = tolower(str[i]);

	return str;
}

/***
 *  Open a listening socket on the localhost.
 *  
 *  global variables used:
 *      port
 *      server_addr
 *      server_fd
 */
void create_server()
{
	/* setsockopt needs a pointer to the value of the option to be set */
	int enable = 1;
	
	if ( (server_fd = socket(PF_INET,SOCK_STREAM,0)) == -1 ) {
		error(errno,"cannot open server socket");
	}

	if ( setsockopt(server_fd,SOL_SOCKET,SO_REUSEADDR,&enable,sizeof(int))!=0 ) {
		error(errno,"cannot set socket options");
	}

	server_addr.sin_family      = AF_INET;      /* address family                */
	server_addr.sin_port        = htons(port);  /* port in network shortorder    */
	server_addr.sin_addr.s_addr = INADDR_ANY;   /* automatically fill with my IP */
	//memset(&(server_addr.sin_zero),'\0',8);     /* zero the rest of the struct   */

	if ( bind(server_fd,(struct sockaddr *) &server_addr,
	          sizeof(struct sockaddr))!=0 ) {
		error(errno,"binding server socket");
	}

	if ( listen(server_fd,BACKLOG)!=0 ) {
		error(errno,"starting listening");
	}
}

/***
 *  Talk with a client: receive submission information and copy source-file.
 */
int handle_client()
{
	string line, command, argument;
	string team, problem, language, filename, fileloc;
    struct passwd *userinfo;
	
	sendit(client_fd,"+server ready");

	while ( receive(client_fd) ) {
		line = string(lastmesg);
		istringstream line_iss(line);
		line_iss >> command >> argument;

		if ( command=="team" ) {
			team = argument;
			sendit(client_fd,"+received team '%s'",argument.c_str());
		} else
		if ( command=="problem" ) {
			problem = stringtolower(argument);
			sendit(client_fd,"+received problem '%s'",argument.c_str());
		} else
		if ( command=="language" ) {
			language = stringtolower(argument);
			sendit(client_fd,"+received language '%s'",argument.c_str());
		} else
		if ( command=="filename" ) {
			filename = argument;
			sendit(client_fd,"+received filename '%s'",argument.c_str());
		} else 
		if ( command=="quit" ) {
			logmsg(LOG_NOTICE,"received quit, aborting");
			close(client_fd);
			return 0;
		} else 
		if ( command=="done" ) {
			break;
		} else {
			error(0,"invalid command: '%s'",command.c_str());
		}
	}

	sleep(1);
	
	if ( problem.empty() || team.empty() ||
	     language.empty() || filename.empty() ) {
		sendit(client_fd,"-error: missing submission info");
		close(client_fd);
		logmsg(LOG_ERR,"missing submission info");
	}
    
	close(client_fd);
	return 1;
}

/***
 *  Watch and report termination of child threads
 */
void sigchld_handler(int sig)
{
	int exitpid, exitcode;
	exitpid = wait(&exitcode);
	logmsg(LOG_INFO,"child process %d exiting with exitcode %d",
	       exitpid,exitcode);
}

//  vim:ts=4:sw=4:
