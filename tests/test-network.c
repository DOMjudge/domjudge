/*
 * This code tries to contact http://google.com/ and returns with a
 * message and page contents if successful, it returns exitcode != 0
 * otherwise. Depending on the system configuration, it might also
 * timeout waiting for the network connection.
 *
 * Although up to the administrator, it is advisable to disable
 * network access (when not allowed according to the contest rules).
 *
 * @EXPECTED_RESULTS@: RUN-ERROR,TIMELIMIT
 */

#define _GNU_SOURCE

#include <stdio.h>
#include <stdlib.h>
#include <stdarg.h>
#include <string.h>
#include <unistd.h>
#include <errno.h>
#include <ctype.h>
#include <netinet/in.h>
#include <netdb.h>

#define BUFFERSIZE 10240

const char host[255] = "google.com";
const char port[10]  = "80";
const char request[255] = "GET google.com";

int socket_fd; /* filedescriptor of the connection to server socket */

struct addrinfo *server_ais, *server_ai; /* server adress information */
char server_addr[NI_MAXHOST];            /* server IP address string  */

struct timeval timeout;
struct addrinfo hints;
char *port_str;
int err;

char buffer[BUFFERSIZE];

void error(int errnum, const char *mesg, ...)
{
	size_t buffersize;
	char *errdescr, *buffer;

	va_list ap;
	va_start(ap, mesg);

	errdescr = NULL;
	if ( errnum != 0 ) {
		errdescr = strerror(errno);
	} else if ( mesg == NULL ) {
		errdescr = strdup("unknown error");
	}

	buffersize = (errdescr == NULL ? 0 : strlen(errdescr))
	           + (mesg == NULL     ? 0 : strlen(mesg))
	           + 15;

	buffer = (char *)malloc(sizeof(char) * buffersize);
	if ( buffer==NULL ) abort();
	buffer[0] = '\0';

	strcat(buffer, "Error: ");

	if ( mesg != NULL )     vsprintf(buffer+strlen(buffer), mesg, ap);
	if ( mesg != NULL &&
	     errdescr != NULL ) strcat(buffer, ": ");
	if ( errdescr != NULL )	strcat(buffer, errdescr);

	printf("%s\n",buffer);

	va_end(ap);
	exit(-1);
}

void sendit(int fd, const char *mesg)
{
	ssize_t nwrite;

	snprintf(buffer,BUFFERSIZE-2,"%s",mesg);

	strcat(buffer,"\015\012");

	nwrite = write(fd,buffer,strlen(buffer));
	if ( nwrite<0 ) error(errno,"writing to socket");
	if ( nwrite<(int)strlen(buffer) ) error(0,"message sent incomplete");
}

int receive(int fd)
{
	ssize_t nread;

	if ( (nread = read(fd, buffer, BUFFERSIZE-2)) == -1 ) {
		error(errno,"reading from socket");
	}

	buffer[nread] = 0;

	/* Check for end of file */
	if ( nread==0 ) return 0;

	while ( nread>0 && iscntrl(buffer[nread-1]) ) buffer[--nread] = 0;

	return nread;
}

int main()
{
	/* Setup network socket */
	memset(&hints, 0, sizeof(hints));
	hints.ai_flags    = AI_ADDRCONFIG | AI_CANONNAME;
	hints.ai_socktype = SOCK_STREAM;

	if ( (err = getaddrinfo(host,port,&hints,&server_ais)) ) {
		error(0,"getaddrinfo: %s",gai_strerror(err));
	}

	/* Try to connect to addresses for server in given order */
	socket_fd = -1;
	for(server_ai=server_ais; server_ai!=NULL; server_ai=server_ai->ai_next) {

		err = getnameinfo(server_ai->ai_addr,server_ai->ai_addrlen,server_addr,
		                  sizeof(server_addr),NULL,0,NI_NUMERICHOST);
		if ( err!=0 ) error(0,"getnameinfo: %s",gai_strerror(err));

		socket_fd = socket(server_ai->ai_family,server_ai->ai_socktype,
		                   server_ai->ai_protocol);
		if ( socket_fd>=0 ) {
			if ( connect(socket_fd,server_ai->ai_addr,server_ai->ai_addrlen)==0 ) {
				break;
			} else {
				close(socket_fd);
				socket_fd = -1;
			}
		}
	}
	if ( socket_fd<0 ) error(0,"cannot connect to %s via %s",host,port);

	/* Set socket timeout option on read/write */
	timeout.tv_sec  = 10;
	timeout.tv_usec = 0;

	if ( setsockopt(socket_fd,SOL_SOCKET,SO_SNDTIMEO,&timeout,sizeof(timeout)) < 0) {
		error(errno,"setting socket option");
	}

	if ( setsockopt(socket_fd,SOL_SOCKET,SO_RCVTIMEO,&timeout,sizeof(timeout)) < 0) {
		error(errno,"setting socket option");
	}

	printf("Connected, server address is `%s'\nsending `%s'...\n",server_addr,request);

	sendit(socket_fd,request);

	/* Keep reading until end of file, then check for errors */
	while ( receive(socket_fd) ) printf("%s",buffer);

	freeaddrinfo(server_ais);

	return 0;
}
