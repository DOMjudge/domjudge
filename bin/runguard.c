/*
   runguard -- run command with restrictions.
   Copyright (C) 2004-2008 Jaap Eldering (eldering@a-eskwadraat.nl).

   Based on an idea from the timeout program, written by Wietse Venema
   as part of The Coroner's Toolkit.
   
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
   Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
   
 */

#include <sys/types.h>
#include <sys/wait.h>
#include <sys/param.h>
#include <sys/time.h>
#include <sys/resource.h>
#include <errno.h>
#include <signal.h>
#include <stdlib.h>
#include <unistd.h>
#include <string.h>
#include <stdarg.h>
#include <stdio.h>
#include <getopt.h>
#include <pwd.h>

/* Some system/site specific config: VALID_USERS and CHROOT_PREFIX are
 * used, maybe include here for standalone executable?
 */
#include "../etc/judgehost-config.h"

#define PROGRAM "runguard"
#define VERSION "0.9"
#define AUTHORS "Jaap Eldering"

extern int errno;

#ifndef _GNU_SOURCE
extern char **environ;
#endif

const int exit_failure = -1;

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
int no_coredump;
int be_verbose;
int be_quiet;
int show_help;
int show_version;

rlim_t memsize;
rlim_t filesize;
rlim_t nproc;

pid_t child_pid;

struct timeval starttime, endtime;

struct option const long_opts[] = {
	{"root",    required_argument, NULL,         'r'},
	{"time",    required_argument, NULL,         't'},
	{"user",    required_argument, NULL,         'u'},
	{"memsize", required_argument, NULL,         'm'},
	{"filesize",required_argument, NULL,         'f'},
	{"nproc",   required_argument, NULL,         'p'},
	{"no-core", no_argument,       NULL,         'c'},
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
	printf("  -r, --root=ROOT      run COMMAND with root directory set to ROOT\n");
	printf("  -t, --time=TIME      kill COMMAND if still running after TIME seconds\n");
	printf("  -u, --user=USER      run COMMAND as user with username or ID USER\n");
	printf("  -m, --memsize=SIZE   set all (total, stack, etc) memory limits to SIZE kB\n");
	printf("  -f, --filesize=SIZE  set maximum created filesize to SIZE kB\n");
	printf("  -p, --nproc=N        set maximum no. processes to N\n");
	printf("  -c, --no-core        disable core dumps\n");
	printf("  -o, --output=FILE    write running time to FILE\n");
	printf("                         WARNING: FILE will be overwritten and written\n");
	printf("                         to as USER when using the `user' option\n");
	printf("  -v, --verbose        display some extra warnings and information\n");
	printf("  -q, --quiet          suppress all warnings and verbose output\n");
	printf("      --help           display this help and exit\n");
	printf("      --version        output version information and exit\n");
	printf("\n");
	printf("Note that root privileges are needed for the `root' and `user' options.\n");
	printf("When run setuid without the `user' option, the user ID is set to the\n");
	printf("real user ID.\n");
	exit(0);
}

void outputtime()
{
	double timediff; /* in seconds */

	if ( gettimeofday(&endtime,NULL) ) error(errno,"getting time");
	
	timediff = (endtime.tv_sec  - starttime.tv_sec ) +
	           (endtime.tv_usec - starttime.tv_usec)*1E-6;
	
	verbose("runtime is %.3lf seconds",timediff);

	if ( use_output ) {
		if ( fprintf(outputfile,"%.3lf\n",timediff)==0 ) {
			error(0,"cannot write to file `%s'",outputfile);
		}
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

	/* First try to kill graciously, then hard */
	verbose("sending signal TERM");
	killpg(child_pid,SIGTERM);
	
	sleep(1);

	verbose("sending signal KILL");
	killpg(child_pid,SIGKILL);
}

int userid(char *name)
{
	struct passwd *pwd;

	errno = 0; /* per the linux GETPWNAM(3) man-page */
	pwd = getpwnam(name);

	if ( pwd==NULL || errno ) return -1;

	return (int) pwd->pw_uid;
}

inline long readoptarg(const char *desc, long minval, long maxval)
{
	long arg;
	char *ptr;
	
	arg = strtol(optarg,&ptr,10);
	if ( errno || *ptr!='\0' || arg<minval || arg>maxval ) {
		error(errno,"invalid %s specified: `%s'",desc,optarg);
	}

	return arg;
}

int main(int argc, char **argv)
{
	sigset_t oldmask, newmask;
	pid_t pid;
	int   status;
	int   exitcode;
	char *valid_users;
	char *ptr;
	char *path;
	char  cwd[MAXPATHLEN+3];
	int   opt;
	
	struct rlimit lim;
		
	progname = argv[0];

	/* Clear environment to prevent all kinds of security holes, save PATH */
	path = getenv("PATH");
	environ[0] = NULL;
	/* FIXME: Clean path before setting it again? */
	if ( path!=NULL ) setenv("PATH",path,1);

	/* Parse command-line options */
	use_root = use_time = use_user = use_output = no_coredump = 0;
	memsize = filesize = nproc = RLIM_INFINITY;
	be_verbose = be_quiet = 0;
	show_help = show_version = 0;
	opterr = 0;
	while ( (opt = getopt_long(argc,argv,"+r:t:u:m:f:p:co:vq",long_opts,(int *) 0))!=-1 ) {
		switch ( opt ) {
		case 0:   /* long-only option */
			break;
		case 'r': /* rootdir option */
			use_root = 1;
			rootdir = (char *) malloc(strlen(optarg)+2);
			strcpy(rootdir,optarg);
			break;
		case 't': /* time option */
			use_time = 1;
			runtime = readoptarg("time",1,LONG_MAX);
			break;
		case 'u': /* user option: uid or string */
			use_user = 1;
			runuid = strtol(optarg,&ptr,10);
			if ( errno || *ptr!='\0' ) runuid = userid(optarg);
			if ( runuid<0 ) error(0,"invalid username or ID specified: `%s'",optarg);
			break;
		case 'm': /* memsize option */
			memsize = (rlim_t) readoptarg("memory limit",1,LONG_MAX);
			/* Convert limit from kB to bytes and check for overflow */
			if ( memsize!=(memsize*1024)/1024 ) {
				memsize = RLIM_INFINITY;
			} else {
				memsize *= 1024;
			}
			break;
		case 'f': /* filesize option */
			filesize = (rlim_t) readoptarg("filesize limit",1,LONG_MAX);
			/* Convert limit from kB to bytes and check for overflow */
			if ( filesize!=(filesize*1024)/1024 ) {
				filesize = RLIM_INFINITY;
			} else {
				filesize *= 1024;
			}
			break;
		case 'p': /* nproc option */
			nproc = (rlim_t) readoptarg("process limit",1,LONG_MAX);
			break;
		case 'c': /* no-core option */
			no_coredump = 1;
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
			error(0,"getopt returned character code `%c' ??",(char)opt);
		}
	}

	if ( show_help ) usage();
	if ( show_version ) version();
	
	if ( argc<=optind ) error(0,"no command specified");

	/* Command to be executed */
	cmdname = argv[optind];
	cmdargs = argv+optind;

	/* Check that new uid is in list of valid uid's.
	   This must be done before chroot for /etc/passwd lookup. */
	if ( use_user ) {
		valid_users = strdup(VALID_USERS);
		for(ptr=strtok(valid_users,","); ptr!=NULL; ptr=strtok(NULL,",")) {
			if ( runuid==userid(ptr) ) break;
		}
		if ( ptr==NULL || runuid<=0 ) error(0,"illegal user specified: %d",runuid);
	}

	/* Set resource limits: must be root to raise hard limits.
	   Note that limits can thus be raised from the systems defaults! */
	
	/* First define shorthand macro function */
#define setlim(type) \
	if ( setrlimit(RLIMIT_ ## type, &lim)!=0 ) { \
		if ( errno==EPERM ) { \
			warning("no permission to set resource RLIMIT_" #type); \
		} else { \
			error(errno,"setting resource RLIMIT_" #type); \
		} \
	}

	if ( memsize!=RLIM_INFINITY ) {
		verbose("setting memory limits to %d bytes",(int)memsize);
	}
	lim.rlim_cur = lim.rlim_max = memsize;
	setlim(AS);
	setlim(DATA);
	setlim(STACK);
	setlim(MEMLOCK);
	
	if ( filesize!=RLIM_INFINITY ) {
		verbose("setting filesize limit to %d bytes",(int)filesize);
	}
	lim.rlim_cur = lim.rlim_max = filesize;
	setlim(FSIZE);
	
	if ( nproc!=RLIM_INFINITY ) {
		verbose("setting process limit to %d",(int)nproc);
	}
	lim.rlim_cur = lim.rlim_max = nproc;
	setlim(NPROC);

#undef setlim
	
	if ( no_coredump ) {
		verbose("disabling core dumps");
		lim.rlim_cur = lim.rlim_max = 0;
		if ( setrlimit(RLIMIT_CORE,&lim)!=0 ) error(errno,"disabling core dumps");
	}
	
	/* Set root-directory and change directory to there. */
	if ( use_root ) {
		/* Small security issue: when running setuid-root, people can find
		   out which directories exist from error message. */
		if ( chdir(rootdir) ) error(errno,"cannot chdir to `%s'",rootdir);

		/* Get absolute pathname of rootdir, by reading it. */
		if ( getcwd(cwd,MAXPATHLEN)==NULL ) error(errno,"cannot get directory");
		if ( cwd[strlen(cwd)-1]!='/' ) strcat(cwd,"/");

		/* Canonicalize CHROOT_PREFIX: the use of NULL below is a GNU
		   extension, recommended for security */
		if ( (path = realpath(CHROOT_PREFIX,NULL))==NULL ) {
			error(errno,"cannot canonicalize path '%s'",CHROOT_PREFIX);
		}
		
		/* Check that we are within prescribed path. */
		if ( strncmp(cwd,path,strlen(path))!=0 ) {
			error(0,"invalid root: must be within `%s'",path);
		}
		free(path);
		
		if ( chroot(".") ) error(errno,"cannot change root to `%s'",cwd);
		verbose("using root-directory `%s'",cwd);
	}
	
	/* Set user-id (must be root for this). */
	if ( use_user ) {
		if ( setuid(runuid) ) error(errno,"cannot set user ID to `%d'",runuid);
		verbose("using user ID `%d'",runuid);
	} else {
		/* Reset effective uid to real uid, to increase security
		   when program is run setuid */
		if ( setuid(getuid()) ) error(errno,"cannot set real user ID");
		verbose("using real uid `%d' as effective uid",getuid());
	}
	if ( geteuid()==0 || getuid()==0 ) error(0,"root privileges not dropped");

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
		   and all its children can be killed off with one signal. */
		setsid();
		execvp(cmdname,cmdargs);
		error(errno,"cannot start `%s'",cmdname);
		
	default: /* become watchdog */
		if ( gettimeofday(&starttime,NULL) ) error(errno,"getting time");

		/* unmask all signals */
		memset(&newmask, 0, sizeof(newmask));
		if ( sigprocmask(SIG_SETMASK, &newmask, &oldmask)!=0 ) {
			error(errno,"unmasking signals");
		}

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
