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

/* Some system/site specific config */
#include "../etc/config.h"

/* Logging and error functions */
#include "../lib/lib.error.h"

#define PROGRAM "submitdaemon"
#define VERSION "0.1"
#define AUTHORS "Peter van de Werken & Jaap Eldering"

#define BACKLOG 32      // how many pending connections queue will hold

extern int errno;

/* Variables defining logmessages verbosity to stderr/logfile */
int  verbose      = LOG_NOTICE;
int  loglevel     = LOG_DEBUG;
char logfile[255] = LOGDIR"/submit.log"
FILE *stdlog;
char *progname;

int port = SUBMITPORT;

int show_help;
int show_version;

struct option const long_opts[] = {
	{"port",    required_argmuent, NULL,         'P'},
	{"verbose", required_argument, NULL,         'v'},
	{"help",    no_argument,       &show_help,    1 },
	{"version", no_argument,       &show_version, 1 },
	{ NULL,     0,                 NULL,          0 }
};

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
void handle_client(int);
void sigchld_handler(int);

const int false = 0;
const int true  = 1;

int server_fd, client_fd;       /* server/client socket filedescriptors */
struct sockaddr_in server_addr; /* server address information */
struct sockaddr_in client_addr; /* client address information */
int sin_size;

int main(int argc, char **argv)
{
    struct sigaction sigchildaction;
    int child_pid;
	int c;
	
	progname = argv[0];

	/* Parse command-line options */
	show_help = show_version = 0;
	opterr = 0;
	while ( (c = getopt_long(argc,argv,"P:v:q",long_opts,NULL)!=-1 ) {
		switch ( c ) {
		case 0:   /* long-only option */
			break;
		case 'P': /* port option */
			port = strtol(optarg,&ptr,10);
			if ( *ptr!=0 || port<=0 || port>65535 ) {
				error(0,"invalid tcp port specified: `%s'",optarg);
			}
			break;
		case 'v': /* verbose option */
			verbose = strtol(optarg,&ptr,10);
			if ( *ptr!=0 || verbose<=0 ) {
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
	
	if ( argc>optind ) error(0,"");
	
    logmsg(LOG_NOTICE,"server started");
    
    create_server();
    logmsg("listening on port %d/tcp", port);
    
    /* Setup the child signal handler */
    sigchildaction.sa_handler = sigchld_handler;
    sigemptyset(&sigchildaction.sa_mask);
    sigchildaction.sa_flags = SA_RESTART;
    if ( sigaction(SIGCHLD,&sigchildaction,NULL)!=0 ) {
		error(errno,"setting child signal handler");
    }
    
    /* main accept() loop */
    while ( true ) {
        sin_size = sizeof(struct sockaddr_in);
		
        if ( (client_fd = accept(server_fd, (struct sockaddr *) &client_addr,
		                         &sin_size))!=0 ) {
			warning(errno,"accepting incoming connection");
            continue;
        }
        
        logmsg(LOG_INFO,"incoming connection, spawning child");
        
        switch ( child_pid = fork() ) {
		case -1: /* error */
			error(errno,"cannot fork");
		
		case  0: /* child thread */
            logmsg(LOG_NOTICE,"connection from %s",inet_ntoa(client_addr.sin_addr));
			
            close(server_fd); /* child doesn't need the listener */
            handle_client(client_fd);
			
			logmsg(LOG_INFO,"child exiting")
            exit(0);
			
		default: /* parent thread */
			logmsg(LOG_DEBUG,"spawned child, pid=%d", child_pid);
			close(client_fd);
		}

	}

    return 0; /* This should never be reached */
}

/*****************************************************************************/

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
	if ( (server_fd = socket(PF_INET,SOCK_STREAM,0))!=0 ) {
		error(errno,"cannot open server socket");
	}

	if ( setsockopt(server_fd,SOL_SOCKET,SO_REUSEADDR,&true,sizeof(int))!=0 ) {
		error(errno,"cannot set socket options");
	}
    
	server_addr.sin_family      = AF_INET;      /* address family                */
	server_addr.sin_port        = htons(port);  /* port in network short order   */
	server_addr.sin_addr.s_addr = INADDR_ANY;   /* automatically fill with my IP */
	//	memset(&(server_addr.sin_zero),'\0',8);     /* zero the rest of the struct   */

	if ( bind(server_fd,(struct sockaddr *) &server_addr,
	          sizeof(struct sockaddr))!=0 ) {
		error(errno,"binding server socket");
	}

	if ( listen(server_fd,BACKLOG)!=0 ) {
		error(errno,"starting listening");
	}
}

/***
 *
 */
void handle_client(int client)
{
	if (send(client, "+hello, send submission info, then files", 40, 0)!=0 )
		perror("send");
	close(new_fd);
}


/***
 *  used to watch termination of child threads
 *  
 *  TODO: return the exit code
 */
void sigchld_handler(int sig)
{
    int exitpid, exitcode;
    exitpid = wait(&exitcode);
    logmsg(LOG_INFO,"child process %d exiting with exitcode %d",
	       exitpid,exitcode);
}

//  vim:ts=4:sw=4:et:
