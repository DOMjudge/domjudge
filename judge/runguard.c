/*
   runguard -- run command with restrictions.
   Copyright (C) 2004-2009 Jaap Eldering (eldering@a-eskwadraat.nl).

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


   Program specifications:

   This program will run the specified command in a separate process
   group (session) and apply the restrictions as specified after
   forking, before executing the command.

   The stdin and stdout streams are passed to the command and runguard
   does not read or write to these. Error and verbose messages from
   runguard are by default written to stderr, hence mixed with stderr
   output of the command.

   The command and its children are sent a SIGTERM after the runtime
   has passed, followed by a SIGKILL after 'killdelay'.
 */

#include "config.h"

/* For chroot(), which is not POSIX. */
#define _BSD_SOURCE

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
#include <grp.h>
#include <time.h>
#include <math.h>
#include <limits.h>

/* Some system/site specific config: VALID_USERS, CHROOT_PREFIX */
#include "runguard-config.h"

#define PROGRAM "runguard"
#define VERSION REVISION
#define AUTHORS "Jaap Eldering"

const struct timespec killdelay = { 0, 100000000L }; /* 0.1 seconds */

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

int runuid;
int rungid;
int use_root;
int use_time;
int use_user;
int use_group;
int use_output;
int no_coredump;
int be_verbose;
int be_quiet;
int show_help;
int show_version;

unsigned long runtime; /* in microseconds */
rlim_t cputime;
rlim_t memsize;
rlim_t filesize;
rlim_t nproc;

pid_t child_pid;

struct timeval starttime, endtime;

struct option const long_opts[] = {
	{"root",    required_argument, NULL,         'r'},
	{"user",    required_argument, NULL,         'u'},
	{"group",   required_argument, NULL,         'g'},
	{"time",    required_argument, NULL,         't'},
	{"cputime", required_argument, NULL,         'C'},
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
		fprintf(stderr,"%s: verbose: ",progname);
		vfprintf(stderr,format,ap);
		fprintf(stderr,"\n");
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
	printf("\
%s -- version %s\n\
Written by %s\n\n\
%s comes with ABSOLUTELY NO WARRANTY.  This is free software, and you\n\
are welcome to redistribute it under certain conditions.  See the GNU\n\
General Public Licence for details.\n",PROGRAM,VERSION,AUTHORS,PROGRAM);
	exit(0);
}

void usage()
{
	printf("\
Usage: %s [OPTION]... COMMAND...\n\
Run COMMAND with restrictions.\n\
\n", progname);
	printf("\
  -r, --root=ROOT      run COMMAND with root directory set to ROOT\n\
  -u, --user=USER      run COMMAND as user with username or ID USER\n\
  -g, --group=GROUP    run COMMAND under group with name or ID GROUP\n\
  -t, --time=TIME      kill COMMAND if still running after TIME seconds (float)\n\
  -C, --cputime=TIME   set maximum CPU time to TIME seconds (integer)\n\
  -m, --memsize=SIZE   set all (total, stack, etc) memory limits to SIZE kB\n\
  -f, --filesize=SIZE  set maximum created filesize to SIZE kB\n");
	printf("\
  -p, --nproc=N        set maximum no. processes to N\n\
  -c, --no-core        disable core dumps\n\
  -o, --output=FILE    write actual runtime to FILE\n\
  -v, --verbose        display some extra warnings and information\n\
  -q, --quiet          suppress all warnings and verbose output\n\
      --help           display this help and exit\n\
      --version        output version information and exit\n");
	printf("\n\
Note that root privileges are needed for the `root' and `user' options.\n\
When run setuid without the `user' option, the user ID is set to the\n\
real user ID.\n");
	exit(0);
}

void outputtime()
{
	FILE  *outputfile;
	double timediff; /* in seconds */

	if ( gettimeofday(&endtime,NULL) ) error(errno,"getting time");

	timediff = (endtime.tv_sec  - starttime.tv_sec ) +
	           (endtime.tv_usec - starttime.tv_usec)*1E-6;

	verbose("runtime is %.3f seconds",timediff);

	if ( use_output ) {
		verbose("writing runtime to file `%s'",outputfilename);

		if ( (outputfile = fopen(outputfilename,"w"))==NULL ) {
			error(errno,"cannot open `%s'",outputfilename);
		}
		if ( fprintf(outputfile,"%.3f\n",timediff)==0 ) {
			error(0,"cannot write to file `%s'",outputfile);
		}
		if ( fclose(outputfile) ) {
			error(errno,"closing file `%s'",outputfilename);
		}
	}
}

void terminate(int sig)
{
	struct sigaction sigact;

	/* Reset signal handlers to default */
	sigact.sa_handler = SIG_DFL;
	sigact.sa_flags = 0;
	if ( sigemptyset(&sigact.sa_mask)!=0 ) {
		warning("could not initialize signal mask");
	}
	if ( sigaction(SIGTERM,&sigact,NULL)!=0 ) {
		warning("could not restore signal handler");
	}
	if ( sigaction(SIGALRM,&sigact,NULL)!=0 ) {
		warning("could not restore signal handler");
	}

	if ( sig==SIGALRM ) {
		warning("timelimit reached: aborting command");
	} else {
		warning("received signal %d: aborting command",sig);
	}

	/* First try to kill graciously, then hard */
	verbose("sending SIGTERM");
	if ( kill(-child_pid,SIGTERM)!=0 ) error(errno,"sending SIGTERM to command");

	/* Prefer nanosleep over sleep because of higher resolution and
	   it does not interfere with signals. */
	nanosleep(&killdelay,NULL);

	verbose("sending SIGKILL");
	if ( kill(-child_pid,SIGKILL)!=0 ) error(errno,"sending SIGKILL to command");
}

int userid(char *name)
{
	struct passwd *pwd;

	errno = 0; /* per the linux GETPWNAM(3) man-page */
	pwd = getpwnam(name);

	if ( pwd==NULL || errno ) return -1;

	return (int) pwd->pw_uid;
}

int groupid(char *name)
{
	struct group *grp;

	errno = 0; /* per the linux GETGRNAM(3) man-page */
	grp = getgrnam(name);

	if ( grp==NULL || errno ) return -1;

	return (int) grp->gr_gid;
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

void setrestrictions()
{
	char *path;
	char  cwd[PATH_MAX+1];

	struct rlimit lim;

	/* Clear environment to prevent all kinds of security holes, save PATH */
	path = getenv("PATH");
	environ[0] = NULL;
	/* FIXME: Clean path before setting it again? */
	if ( path!=NULL ) setenv("PATH",path,1);

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

	if ( cputime!=RLIM_INFINITY ) {
		verbose("setting CPU-time limit to %d seconds",(int)cputime);
		lim.rlim_cur = lim.rlim_max = cputime;
		setlim(CPU);
	}

	if ( memsize!=RLIM_INFINITY ) {
		verbose("setting memory limits to %d bytes",(int)memsize);
		lim.rlim_cur = lim.rlim_max = memsize;
		setlim(AS);
		setlim(DATA);
		setlim(STACK);
	}

	if ( filesize!=RLIM_INFINITY ) {
		verbose("setting filesize limit to %d bytes",(int)filesize);
		lim.rlim_cur = lim.rlim_max = filesize;
		setlim(FSIZE);
	}

	if ( nproc!=RLIM_INFINITY ) {
		verbose("setting process limit to %d",(int)nproc);
		lim.rlim_cur = lim.rlim_max = nproc;
		setlim(NPROC);
	}

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
		if ( chdir(rootdir)!=0 ) error(errno,"cannot chdir to `%s'",rootdir);

		/* Get absolute pathname of rootdir, by reading it. */
		if ( getcwd(cwd,PATH_MAX)==NULL ) error(errno,"cannot get directory");
		if ( cwd[strlen(cwd)-1]!='/' ) strcat(cwd,"/");

		/* Canonicalize CHROOT_PREFIX. */
		if ( (path = (char *) malloc(PATH_MAX+1))==NULL ) {
			error(errno,"allocating memory");
		}
		if ( realpath(CHROOT_PREFIX,path)==NULL ) {
			error(errno,"cannot canonicalize path '%s'",CHROOT_PREFIX);
		}

		/* Check that we are within prescribed path. */
		if ( strncmp(cwd,path,strlen(path))!=0 ) {
			error(0,"invalid root: must be within `%s'",path);
		}
		free(path);

		if ( chroot(".")!=0 ) error(errno,"cannot change root to `%s'",cwd);
		/* Just to make sure and satisfy Coverity scan: */
		if ( chdir("/")!=0 ) error(errno,"cannot chdir to `/' in chroot");
		verbose("using root-directory `%s'",cwd);
	}

	/* Set group-id (must be root for this, so before setting user). */
	if ( use_group ) {
		if ( setgid(rungid) ) error(errno,"cannot set group ID to `%d'",rungid);
		verbose("using group ID `%d'",rungid);
	}
	/* Set user-id (must be root for this). */
	if ( use_user ) {
		if ( setuid(runuid) ) error(errno,"cannot set user ID to `%d'",runuid);
		verbose("using user ID `%d' for command",runuid);
	} else {
		/* Permanently reset effective uid to real uid, to prevent
		   child command from having root privileges.
		   Note that this means that the child runs as the same user
		   as the watchdog process and can thus manipulate it, e.g. by
		   sending SIGSTOP/SIGCONT! */
		if ( setuid(getuid()) ) error(errno,"cannot reset real user ID");
		verbose("reset user ID to `%d' for command",getuid());
	}
	if ( geteuid()==0 || getuid()==0 ) error(0,"root privileges not dropped");
}

int main(int argc, char **argv)
{
	sigset_t sigmask;
	pid_t pid;
	int   status;
	int   exitcode;
	char *valid_users;
	char *ptr;
	int   opt;
	double runtime_d;

	struct itimerval itimer;
	struct sigaction sigact;

	progname = argv[0];

	/* Parse command-line options */
	use_root = use_time = use_user = use_output = no_coredump = 0;
	cputime = memsize = filesize = nproc = RLIM_INFINITY;
	be_verbose = be_quiet = 0;
	show_help = show_version = 0;
	opterr = 0;
	while ( (opt = getopt_long(argc,argv,"+r:u:g:t:C:m:f:p:co:vq",long_opts,(int *) 0))!=-1 ) {
		switch ( opt ) {
		case 0:   /* long-only option */
			break;
		case 'r': /* rootdir option */
			use_root = 1;
			rootdir = (char *) malloc(strlen(optarg)+2);
			strcpy(rootdir,optarg);
			break;
		case 'u': /* user option: uid or string */
			use_user = 1;
			runuid = strtol(optarg,&ptr,10);
			if ( errno || *ptr!='\0' ) runuid = userid(optarg);
			if ( runuid<0 ) error(0,"invalid username or ID specified: `%s'",optarg);
			break;
		case 'g': /* group option: gid or string */
			use_group = 1;
			rungid = strtol(optarg,&ptr,10);
			if ( errno || *ptr!='\0' ) rungid = groupid(optarg);
			if ( rungid<0 ) error(0,"invalid groupname or ID specified: `%s'",optarg);
			break;
		case 't': /* time option */
			use_time = 1;
			runtime_d = strtod(optarg,&ptr);
			if ( errno || *ptr!='\0' ||
			     runtime_d<=0 || runtime_d>=ULONG_MAX*1E-6 ) {
				error(errno,"invalid runtime specified: `%s'",optarg);
			}
			runtime = (unsigned long)(runtime_d*1E6);
			break;
		case 'C': /* CPU time option */
			cputime = (rlim_t) readoptarg("CPU-time limit",1,LONG_MAX);
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
			outputfilename = strdup(optarg);
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

	switch ( child_pid = fork() ) {
	case -1: /* error */
		error(errno,"cannot fork");
	case  0: /* run controlled command */
		/* Run the command in a separate process group so that the command
		   and all its children can be killed off with one signal. */
		if ( setsid()==-1 ) error(errno,"setsid failed");

		/* Apply all restrictions for child process. */
		setrestrictions();

		/* And execute child command. */
		execvp(cmdname,cmdargs);
		error(errno,"cannot start `%s'",cmdname);

	default: /* become watchdog */

		/* Shed privileges, only if not using a separate child uid,
		   because in that case we may need root privileges to kill
		   the child process. Do not use Linux specific setresuid()
		   call with saved set-user-ID. */
		if ( !use_user ) {
			if ( setuid(getuid())!=0 ) error(errno, "setting watchdog uid");
			verbose("watchdog using user ID `%d'",getuid());
		}

		if ( gettimeofday(&starttime,NULL) ) error(errno,"getting time");

		/* unmask all signals */
		if ( sigemptyset(&sigmask)!=0 ) error(errno,"creating signal mask");
		if ( sigprocmask(SIG_SETMASK, &sigmask, NULL)!=0 ) {
			error(errno,"unmasking signals");
		}

		/* Construct one-time signal handler to terminate() for TERM
		   and ALRM signals. */
		if ( sigaddset(&sigmask,SIGALRM)!=0 ||
		     sigaddset(&sigmask,SIGTERM)!=0 ) error(errno,"setting signal mask");

		sigact.sa_handler = terminate;
		sigact.sa_flags   = SA_RESETHAND | SA_RESTART;
		sigact.sa_mask    = sigmask;

		/* Kill child command when we receive SIGTERM */
		if ( sigaction(SIGTERM,&sigact,NULL)!=0 ) {
			error(errno,"installing signal handler");
		}

		if ( use_time ) {
			/* Kill child when we receive SIGALRM */
			if ( sigaction(SIGALRM,&sigact,NULL)!=0 ) {
				error(errno,"installing signal handler");
			}

			/* Trigger SIGALRM via setitimer:  */
			itimer.it_interval.tv_sec  = 0;
			itimer.it_interval.tv_usec = 0;
			itimer.it_value.tv_sec  = runtime / 1000000;
			itimer.it_value.tv_usec = runtime % 1000000;

			if ( setitimer(ITIMER_REAL,&itimer,NULL)!=0 ) {
				error(errno,"setting timer");
			}
			verbose("using timelimit of %.3f seconds",runtime*1E-6);
		}

		/* Wait for the child command to finish */
		while ( (pid = wait(&status))!=-1 && pid!=child_pid );
		if ( pid!=child_pid ) error(errno,"waiting on child");

		/* Drop root before writing to output file. */
		if ( setuid(getuid())!=0 ) error(errno,"dropping root privileges");

		outputtime();

		/* Test whether command has finished abnormally */
		if ( ! WIFEXITED(status) ) {
			if ( WIFSIGNALED(status) ) {
				warning("command terminated with signal %d",WTERMSIG(status));
				return 128+WTERMSIG(status);
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
