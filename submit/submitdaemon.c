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

int show_help;
int show_version;

struct option const long_opts[] = {
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
	printf("  -v, --verbose=LEVEL   set verbosity to LEVEL (syslog levels)\n");
	printf("      --help            display this help and exit\n");
	printf("      --version         output version information and exit\n");
	printf("\n");
	exit(0);
}


void create_server();
void handle_client(int);
void sigchld_handler(int);
void logmsg(char *, ...);
void error(char *, ...);

int yes=1;

int server_fd, new_fd;          // listen on server_fd, new connection on new_fd
struct sockaddr_in server_addr; // my address information
struct sockaddr_in their_addr;  // connector's address information
int sin_size;

int main(int argc, char **argv)
{
    struct sigaction sa;
    int cpid;
	int c;
	
	progname = argv[0];

	/* Parse command-line options */
	show_help = show_version = 0;
	opterr = 0;
	while ( (c = getopt_long(argc,argv,"v:q",long_opts,NULL)!=-1 ) {
		switch ( c ) {
		case 0:   /* long-only option */
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
	
    logmsg("server started");
    
    create_server();
    logmsg("listening on port %i", port);
    
    // reap all dead processes
    sa.sa_handler = sigchld_handler;
    sigemptyset(&sa.sa_mask);
    sa.sa_flags = SA_RESTART;
    if (sigaction(SIGCHLD, &sa, NULL) == -1) {
        perror("sigaction");
        exit(1);
    }
    
    // main accept() loop
    while(1) {
        sin_size = sizeof(struct sockaddr_in);
        if ((new_fd = accept(server_fd
                        , (struct sockaddr *)&their_addr, &sin_size)
            ) == -1) {
            perror("accept");
            continue;
        }
        
        // loadOptions();
        logmsg("incoming connection, spawning child");
        
        cpid = fork();
        
        if (cpid == -1) {
            perror("fork");
        } else if (cpid == 0) {
            // this is the child process
            
            logmsg("connection from %s", inet_ntoa(their_addr.sin_addr));
            
            close(server_fd); // child doesn't need the listener
            
// -- child function
            
            handle_client(new_fd);
            // compile()
            // run()
            // checkoutput()
            
            // Done.  The program is correct.
// --
            exit(0);
        }
        logmsg("spawned %05i", cpid);
        close(new_fd);
    }
    return 0;
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
    if ((server_fd = socket(AF_INET, SOCK_STREAM, 0)) == -1) {
        perror("socket");
        exit(1);
    }

    if (setsockopt(server_fd
                , SOL_SOCKET, SO_REUSEADDR, &yes, sizeof(int)) == -1) {
        perror("setsockopt");
        exit(1);
    }
    
    server_addr.sin_family = AF_INET;           // host byte order
    server_addr.sin_port = htons(port);         // short, network byte order
    server_addr.sin_addr.s_addr = INADDR_ANY;   // automatically fill with my IP
    memset(&(server_addr.sin_zero), '\0', 8);   // zero the rest of the struct
    
    if (bind(server_fd, (struct sockaddr *)&server_addr
                , sizeof(struct sockaddr)) == -1) {
        perror("bind");
        exit(1);
    }
    
    if (listen(server_fd, BACKLOG) == -1) {
        perror("listen");
        exit(1);
    }
}

/***
 *
 */
void handle_client(int client)
{
    if (send(client, "+hello, send submission info, then files", 40, 0) == -1)
        perror("send");
    close(new_fd);
}           


/***
 *  used to kill of (child) threads
 *  
 *  TODO: return the exit code
 */
void sigchld_handler(int s)
{
    int exitpid, exitcode;
    exitpid = wait(&exitcode);
    logmsg("reaped process %05i with exit code %i", exitpid, exitcode);
}

//  vim:ts=4:sw=4:et:
