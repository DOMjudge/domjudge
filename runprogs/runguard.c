/*
   runguard -- run command with optional restrictions: time, root, user
   Written by Jaap Eldering, April 2004
   
   Based on:
   chroot   - written by Roland McGrath
   timeout  - written by Wietse Venema


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

#include <sys/types.h>
#include <sys/wait.h>
#include <sys/param.h>
#include <sys/time.h>
#include <errno.h>
#include <signal.h>
#include <stdlib.h>
#include <unistd.h>
#include <string.h>
#include <stdarg.h>
#include <stdio.h>
#include <getopt.h>
#include <pwd.h>

/* Some system/site specific config */
#include "../etc/config.h"

#define PROGRAM "runguard"
#define VERSION "0.1"
#define AUTHORS "Jaap Eldering"

extern int errno;

const int exit_failure = -1;
const int kill_signal = SIGKILL;

char  *progname;
char  *cmdname;
char **cmdargs;
char  *rootdir;
char  *outputfilename;
FILE  *outputfile;

int runuid;
int runtime;
int use_root;
int use_time;
int use_user;
int use_output;
int be_verbose;
int be_quiet;
int show_help;
int show_version;

pid_t child_pid;

struct timeval starttime, endtime;

struct option const long_opts[] = {
	{"root",    required_argument, NULL,         'r'},
	{"time",    required_argument, NULL,         't'},
	{"user",    required_argument, NULL,         'u'},
	{"output",  required_argument, NULL,         'o'},
	{"verbose", no_argument,       NULL,         'v'},
	{"quiet",   no_argument,       NULL,         'q'},
	{"help",    no_argument,       &show_help,    1 },
	{"version", no_argument,       &show_version, 1 },
	{ NULL,     0,                 NULL,          0 }
};

void warning(const char *format, ...)
{
	va_list ap;
	va_start(ap,format);

	if ( ! be_quiet ) {
	    fprintf(stderr,"%s: warning: ",progname);
		vfprintf(stderr,format,ap);
		fprintf(stderr,"\n");
	}
	
	va_end(ap);
}

void verbose(const char *format, ...)
{
	va_list ap;
	va_start(ap,format);

	if ( ! be_quiet && be_verbose ) {
	    printf("%s: verbose: ",progname);
		vprintf(format,ap);
		printf("\n");
	}
	
	va_end(ap);
}

void error(int errnum, const char *format, ...)
{
	va_list ap;
	va_start(ap,format);
	
	fprintf(stderr,"%s",progname);
	
	if ( format!=NULL ) {
		fprintf(stderr,": ");
		vfprintf(stderr,format,ap);
	}
	if ( errnum!=0 ) {
		fprintf(stderr,": %s",strerror(errnum));
	}
	if ( format==NULL && errnum==0 ) {
		fprintf(stderr,": unknown error");
	}
	
	fprintf(stderr,"\nTry `%s --help' for more information.\n",progname);
	va_end(ap);
	
	exit(exit_failure);
}

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
	printf("Usage: %s [OPTION]... COMMAND...\n",progname);
	printf("Run COMMAND with restrictions.\n\n");
	printf("  -r, --root=ROOT     run COMMAND with root directory set to ROOT\n");
	printf("  -t, --time=TIME     kill COMMAND if still running after TIME seconds\n");
	printf("  -u, --user=USER     run COMMAND as user with username or id USER\n");
	printf("  -o, --output=FILE   write running time to FILE\n");
	printf("                        WARNING: FILE will be overwritten and written\n");
	printf("                        to as USER, when using the `user' option\n");
	printf("  -v, --verbose       display some extra warnings and information\n");
	printf("  -q, --quiet         suppress all warnings and verbose output\n");
	printf("      --help          display this help and exit\n");
	printf("      --version       output version information and exit\n");
	printf("\n");
	printf("Note that root privileges are needed for the `root' and `user' options.\n");
	printf("When run setuid root without the `user' option, the user id is set to the");
	printf("real user id.\n");
	exit(0);
}

void outputtime()
{
	int timediff; /* in milliseconds */

	if ( gettimeofday(&endtime,NULL) ) error(errno,"getting time");
	
	timediff = (endtime.tv_sec  - starttime.tv_sec )*1000 +
	           (endtime.tv_usec - starttime.tv_usec)/1000;
	
	verbose("runtime is %d.%03d seconds",timediff/1000,timediff%1000);

	if ( use_output ) {
		fprintf(outputfile,"%d.%03d\n",timediff/1000,timediff%1000);
		if ( fclose(outputfile) ) error(errno,"closing file `%s'",outputfile);
	}
}

void terminate(int sig)
{
	/* Reset signal handlers to default */
	signal(SIGTERM,SIG_DFL);
	signal(SIGALRM,SIG_DFL);
	
	if ( sig==SIGALRM ) {
		warning("timelimit reached: aborting command");
	} else {
		warning("received signal %d: aborting command",sig);
	}
	
	if ( killpg(child_pid,kill_signal) ) error(errno,"cannot kill command");
}

int main(int argc, char **argv)
{
	pid_t pid;
	int   status;
	int   exitcode;
	char *ptr;
	char  cwd[MAXPATHLEN+3];
	char  c;
	int   i;
	
	struct passwd *pwd;
	
	progname = argv[0];

	/* Parse command-line options */
	use_root = use_time = use_user = use_output = 0;
	be_verbose = be_quiet = 0;
	show_help = show_version = 0;
	opterr = 0;
	while ( (c = getopt_long(argc,argv,"+r:t:u:o:vq",long_opts,(int *) 0))!=-1 ) {
		switch ( c ) {
		case 0:   /* long-only option */
			break;
		case 'r': /* rootdir option */
			use_root = 1;
			rootdir = (char *) malloc(strlen(optarg)+2);
			strcpy(rootdir,optarg);
			break;
		case 't': /* time option */
			use_time = 1;
			runtime = strtol(optarg,&ptr,10);
			if ( *ptr!=0 || runtime<=0 ) {
				error(0,"invalid time specified: `%s'",optarg);
			}
			break;
		case 'u': /* user option */
			use_user = 1;
			runuid = strtol(optarg,&ptr,10);
			if ( *ptr!=0 ) {
				runuid = -1;
				pwd = getpwnam(optarg);
				if ( pwd!=NULL ) runuid = pwd->pw_uid;
			}
			if ( runuid<0 ) error(0,"invalid username or id specified: `%s'",optarg);
			break;
		case 'o': /* output option */
			use_output = 1;
			outputfilename = (char *) malloc(strlen(optarg)+2);
			strcpy(outputfilename,optarg);
			break;
		case 'v': /* verbose option */
			be_verbose = 1;
			break;
		case 'q': /* quiet option */
			be_quiet = 1;
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
	
	if ( argc<=optind ) error(0,"no command specified");

	/* Command to be executed */
	cmdname = argv[optind];
	cmdargs = argv+optind;
	
	/* Set root-directory and change directory to there. */
	if ( use_root ) {
		/* Small security issue: when running setuid-root, people can find
		   out which directories exist from error message. */
		if ( chdir(rootdir) ) error(errno,"cannot chdir to `%s'",rootdir);

		/* Get absolute pathname of rootdir, by reading it. */
		if ( getcwd(cwd,MAXPATHLEN)==NULL ) error(errno,"cannot get directory");
		if ( cwd[strlen(cwd)-1]!='/' ) strcat(cwd,"/");

		/* Check that we are within prescribed path. */
		if ( strncmp(cwd,ROOT_PREFIX,strlen(ROOT_PREFIX))!=0 ) {
			error(0,"invalid root: must be within `%s'",ROOT_PREFIX);
		}
		
		if ( chroot(".") ) error(errno,"cannot change root to `%s'",cwd);
		verbose("using root-directory `%s'",cwd);
	}
	
	/* Set user-id (must be root for this). */
	if ( use_user ) {
		/* Check that new uid is in list of valid uid's */
		for(i=0; valid_uid[i]!=-1; i++) if ( runuid==valid_uid[i] ) break;
		if ( valid_uid[i]<=0 ) error(0,"illegal user specified: %d",runuid);
		
		if ( setuid(runuid) ) error(errno,"cannot set user id to `%d'",runuid);
		if ( geteuid()==0 || getuid()==0 ) error(0,"root privileges not dropped");
		verbose("using user id `%d'",runuid);
	} else {
		/* Check if this program is run as (setuid) root and set effective uid
		   to real uid, to increase security */
		if ( geteuid()==0 ) {
			if ( setuid(getuid()) ) error(errno,"cannot set user id");
			verbose("using user id `%d'",getuid());
		}
	}

	/* Open output file for writing running time to */
	if ( use_output ) {
		outputfile = fopen(outputfilename,"w");
		if ( outputfile==NULL ) error(errno,"cannot open `%s'",outputfilename);
		verbose("using file `%s' to write runtime to",outputfilename);
	}
	
	switch ( child_pid = fork() ) {
	case -1: /* error */
		error(errno,"cannot fork");
		
	case  0: /* run controlled command */
		/* Run the command in a separate process group so that the command
		   and all it's children can be killed off with one signal. */
		setsid();
		execvp(cmdname,cmdargs);
		error(errno,"cannot start `%s'",cmdname);
		
	default: /* become watchdog */
		if ( gettimeofday(&starttime,NULL) ) error(errno,"getting time");
		
		signal(SIGTERM,terminate);
		
		if ( use_time ) {
			signal(SIGALRM,terminate);
			alarm(runtime);
			verbose("using timelimit of %d seconds",runtime);
		}

		/* Wait for the child command to finish */
		while ( (pid = wait(&status))!=-1 && pid!=child_pid );
		if ( pid!=child_pid ) error(errno,"waiting on child");

		outputtime();

		/* Test whether command has finished abnormally */
		if ( ! WIFEXITED(status) ) {
			if ( WIFSIGNALED(status) ) {
				warning("command terminated with signal %d",WTERMSIG(status));
				return 128+WTERMSIG(status);
			}
			if ( WIFSTOPPED(status) ) {
				warning("command stopped with signal %d",WSTOPSIG(status));
				return 128+WSTOPSIG(status);
			}
			error(0,"command exit status unknown: %d",status);
		}
		
		/* Return the exitstatus of the command */
		exitcode = WEXITSTATUS(status);
		if ( exitcode!=0 ) verbose("command exited with exitcode %d",exitcode);
		return exitcode; 
	}

	/* This should never be reached */
	error(0,"unexpected end of program");
}
