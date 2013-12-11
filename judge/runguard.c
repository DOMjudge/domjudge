/*
   runguard -- run command with restrictions.

   Part of the DOMjudge Programming Contest Jury System and licenced
   under the GNU GPL. See README and COPYING for details.

   Multiple minor improvements ported from the DOMjudge-ETH tree.

   Based on an idea from the timeout program, written by Wietse Venema
   as part of The Coroner's Toolkit.


   Program specifications:

   This program will run the specified command in a separate process
   group (session) and apply the restrictions as specified after
   forking, before executing the command.

   The stdin and stdout streams are passed to the command and runguard
   does not read or write to these. Error and verbose messages from
   runguard are by default written to stderr, hence mixed with stderr
   output of the command, unless that is optionally redirected to file.

   The command and its children are sent a SIGTERM after the runtime
   has passed, followed by a SIGKILL after 'killdelay'.
 */

#include "config.h"

/* Some system/site specific config: VALID_USERS, CHROOT_PREFIX */
#include "runguard-config.h"

/* For chroot(), which is not POSIX. */
#define _BSD_SOURCE
/* For unshare(), only used when cgroups are enabled */
#if ( USE_CGROUPS == 1 )
#define _GNU_SOURCE
#endif

#include <sys/types.h>
#include <sys/wait.h>
#include <sys/param.h>
#include <sys/select.h>
#include <sys/stat.h>
#include <sys/time.h>
#include <sys/times.h>
#include <sys/resource.h>
#include <errno.h>
#include <fcntl.h>
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
#if ( USE_CGROUPS == 1 )
#include <inttypes.h>
#include <libcgroup.h>
#include <sched.h>
#else
#undef USE_CGROUPS
#endif

#define PROGRAM "runguard"
#define VERSION DOMJUDGE_VERSION "/" REVISION

#define max(x,y) ((x) > (y) ? (x) : (y))

/* Array indices for input/output file descriptors as used by pipe() */
#define PIPE_IN  1
#define PIPE_OUT 0

#define BUF_SIZE 4*1024
char buf[BUF_SIZE];

/* Types of time for writing to file. */
#define WALL_TIME_TYPE 0
#define CPU_TIME_TYPE  1

/* Strings to write to file when exceeding no/soft/hard/both limits. */
const char output_timelimit_str[4][16] = {
	"",
	"soft-timelimit",
	"hard-timelimit",
	"hard-timelimit"
};
/* Bitmask of soft/hard timelimit (used in array above and
 * {wall,cpu}timelimit_reached variabled below). */
const int soft_timelimit = 1;
const int hard_timelimit = 2;

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
char  *stdoutfilename;
char  *stderrfilename;
char  *exitfilename;
char  *timefilename;
char  *metafilename;
#ifdef USE_CGROUPS
char  *cgroupname;
const char *cpuset;

/* Linux Out-Of-Memory adjustment for current process. */
#define OOM_PATH_NEW "/proc/self/oom_score_adj"
#define OOM_PATH_OLD "/proc/self/oom_adj"
#define OOM_RESET_VALUE 0
#endif

int runuid;
int rungid;
int use_root;
int use_walltime;
int use_cputime;
int use_user;
int use_group;
int redir_stdout;
int redir_stderr;
int limit_streamsize;
int outputexit;
int outputmeta;
int outputtime;
int outputtimetype;
int no_coredump;
int be_verbose;
int be_quiet;
int show_help;
int show_version;

double walltime[2], cputime[2]; /* in seconds, soft and hard limits */
int walllimit_reached, cpulimit_reached; /* 1=soft, 2=hard, 3=both limits reached */
#ifdef USE_CGROUPS
int64_t memsize;
#else
rlim_t memsize;
#endif
rlim_t filesize;
rlim_t nproc;
size_t streamsize;

pid_t child_pid;

static volatile sig_atomic_t received_SIGCHLD = 0;

FILE *child_stdout;
FILE *child_stderr;
int child_pipefd[3][2];
int child_redirfd[3];

struct timeval starttime, endtime;
struct tms startticks, endticks;

struct option const long_opts[] = {
	{"root",       required_argument, NULL,         'r'},
	{"user",       required_argument, NULL,         'u'},
	{"group",      required_argument, NULL,         'g'},
	{"walltime",   required_argument, NULL,         't'},
	{"cputime",    required_argument, NULL,         'C'},
	{"memsize",    required_argument, NULL,         'm'},
	{"filesize",   required_argument, NULL,         'f'},
	{"nproc",      required_argument, NULL,         'p'},
	{"cpuset",     required_argument, NULL,         'P'},
	{"no-core",    no_argument,       NULL,         'c'},
	{"stdout",     required_argument, NULL,         'o'},
	{"stderr",     required_argument, NULL,         'e'},
	{"streamsize", required_argument, NULL,         's'},
	{"outexit",    required_argument, NULL,         'E'},
	{"outtime",    required_argument, NULL,         'T'},
	{"outmeta",    required_argument, NULL,         'M'},
	{"verbose",    no_argument,       NULL,         'v'},
	{"quiet",      no_argument,       NULL,         'q'},
	{"help",       no_argument,       &show_help,    1 },
	{"version",    no_argument,       &show_version, 1 },
	{ NULL,        0,                 NULL,          0 }
};

void warning(   const char *, ...) __attribute__((format (printf, 1, 2)));
void verbose(   const char *, ...) __attribute__((format (printf, 1, 2)));
void error(int, const char *, ...) __attribute__((format (printf, 2, 3)));
void write_meta(const char*, const char *, ...) __attribute__((format (printf, 2, 3)));

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

	write_meta("internal-error","%s","runguard error");

	exit(exit_failure);
}

void write_meta(const char *key, const char*format, ...) {
	FILE  *outputfile;
	va_list ap;
	va_start(ap,format);
	if ( !outputmeta ) {
		return;
	}
	if ( (outputfile = fopen(metafilename,"a"))==NULL ) {
		error(errno,"cannot open `%s'",metafilename);
	}


	if ( fprintf(outputfile,"%s: ",key)<=0 ) {
		error(0,"cannot write to file `%s'",metafilename);
	}
	if ( vfprintf(outputfile,format,ap)<0 ) {
		error(0,"cannot write to file `%s'(vfprintf)",metafilename);
	}
	if ( fprintf(outputfile,"\n")<=0 ) {
		error(0,"cannot write to file `%s'",metafilename);
	}



	if ( fclose(outputfile) ) {
		error(errno,"closing file `%s'",metafilename);
	}
}

void version(const char *prog, const char *vers)
{
	printf("\
%s -- part of DOMjudge version %s\n\
Written by the DOMjudge developers\n\n\
DOMjudge comes with ABSOLUTELY NO WARRANTY.  This is free software, and you\n\
are welcome to redistribute it under certain conditions.  See the GNU\n\
General Public Licence for details.\n", prog, vers);
	exit(0);
}

void usage()
{
	printf("\
Usage: %s [OPTION]... COMMAND...\n\
Run COMMAND with restrictions.\n\
\n", progname);
	printf("\
  -r, --root=ROOT        run COMMAND with root directory set to ROOT\n\
  -u, --user=USER        run COMMAND as user with username or ID USER\n\
  -g, --group=GROUP      run COMMAND under group with name or ID GROUP\n\
  -t, --walltime=TIME    kill COMMAND after TIME wallclock seconds\n\
  -C, --cputime=TIME     set maximum CPU time to TIME seconds\n\
  -m, --memsize=SIZE     set all (total, stack, etc) memory limits to SIZE kB\n\
  -f, --filesize=SIZE    set maximum created filesize to SIZE kB;\n");
	printf("\
  -p, --nproc=N          set maximum no. processes to N\n\
  -P, --cpuset=ID        use only processor number ID\n\
  -c, --no-core          disable core dumps\n\
  -o, --stdout=FILE      redirect COMMAND stdout output to FILE\n\
  -e, --stderr=FILE      redirect COMMAND stderr output to FILE\n\
  -s, --streamsize=SIZE  truncate COMMAND stdout/stderr streams at SIZE kB\n\
  -E, --outexit=FILE     write COMMAND exitcode to FILE\n\
  -T, --outtime=FILE     write COMMAND runtime to FILE\n\
  -M, --outmeta=FILE     write COMMAND metadata to FILE\n");
	printf("\
  -v, --verbose          display some extra warnings and information\n\
  -q, --quiet            suppress all warnings and verbose output\n\
      --help             display this help and exit\n\
      --version          output version information and exit\n");
	printf("\n\
Note that root privileges are needed for the `root' and `user' options.\n\
The COMMAND path is relative to the changed ROOT directory if specified.\n\
TIME may be specified as a float; two floats separated by `:' are treated\n\
as soft and hard limits. The runtime written to file is that of the last\n\
of wall/cpu time options set, and defaults to CPU time when neither is set.\n\
When run setuid without the `user' option, the user ID is set to the\n\
real user ID.\n");
	exit(0);
}

void output_exit_time(int exitcode)
{
	FILE  *outputfile;
	double walldiff, cpudiff, userdiff, sysdiff, outdiff;
	int timelimit_reached;
	unsigned long ticks_per_second = sysconf(_SC_CLK_TCK);

	verbose("command exited with exitcode %d",exitcode);
	write_meta("exitcode","%d",exitcode);

	if ( outputexit ) {
		verbose("writing exitcode to file `%s'",exitfilename);

		if ( (outputfile = fopen(exitfilename,"w"))==NULL ) {
			error(errno,"cannot open `%s'",exitfilename);
		}
		if ( fprintf(outputfile,"%d\n",exitcode)<=0 ) {
			error(0,"cannot write to file `%s'",exitfilename);
		}
		if ( fclose(outputfile) ) {
			error(errno,"closing file `%s'",exitfilename);
		}
	}

	walldiff = (endtime.tv_sec  - starttime.tv_sec ) +
	           (endtime.tv_usec - starttime.tv_usec)*1E-6;

	userdiff = (double)(endticks.tms_cutime - startticks.tms_cutime) / ticks_per_second;
	sysdiff  = (double)(endticks.tms_cstime - startticks.tms_cstime) / ticks_per_second;
	cpudiff = userdiff + sysdiff;

	write_meta("wall-time","%.3f", walldiff);
	write_meta("user-time","%.3f", userdiff);
	write_meta("sys-time", "%.3f", sysdiff);
	write_meta("cpu-time", "%.3f", cpudiff);

	verbose("runtime is %.3f seconds real, %.3f user, %.3f sys",
	        walldiff, userdiff, sysdiff);

	if ( use_walltime && walldiff > walltime[0] ) {
		walllimit_reached |= soft_timelimit;
		warning("timelimit exceeded (soft wall time)");
	}

	if ( use_cputime && cpudiff > cputime[0] ) {
		cpulimit_reached |= soft_timelimit;
		warning("timelimit exceeded (soft cpu time)");
	}

	if ( outputtime ) {
		verbose("writing runtime to file `%s'",timefilename);
		switch ( outputtimetype ) {
		case WALL_TIME_TYPE:
			outdiff = walldiff;
			timelimit_reached = walllimit_reached;
			break;
		case CPU_TIME_TYPE:
			outdiff = cpudiff;
			timelimit_reached = cpulimit_reached;
			break;
		default:
			error(0,"cannot write unknown time type `%d' to file",outputtimetype);
		}
		/* Hard limitlimit reached always has precedence. */
		if ( (walllimit_reached | cpulimit_reached) & hard_timelimit ) {
			timelimit_reached |= hard_timelimit;
		}

		if ( (outputfile = fopen(timefilename,"w"))==NULL ) {
			error(errno,"cannot open `%s'",timefilename);
		}
		if ( fprintf(outputfile,"%.3f %s\n",outdiff,
		             output_timelimit_str[timelimit_reached])<=0 ) {
			error(0,"cannot write to file `%s'",timefilename);
		}
		if ( fclose(outputfile) ) {
			error(errno,"closing file `%s'",timefilename);
		}

		write_meta("time-result","%s",output_timelimit_str[timelimit_reached]);
	}
}

#ifdef USE_CGROUPS
void output_cgroup_stats()
{
	int ret;
	int64_t max_usage;
	struct cgroup *cg;
	struct cgroup_controller *cg_controller;

	cg = cgroup_new_cgroup(cgroupname);
	if (!cg) {
		error(0,"cgroup_new_cgroup");
	}
	if ((ret = cgroup_get_cgroup(cg)) != 0) {
		error(0,"get cgroup information: %s(%d)", cgroup_strerror(ret), ret);
	}
	cg_controller = cgroup_get_controller(cg, "memory");
	ret = cgroup_get_value_int64(cg_controller, "memory.memsw.max_usage_in_bytes", &max_usage);
	if ( ret!=0 ) {
		error(0,"get cgroup value: %s(%d)", cgroup_strerror(ret), ret);
	}

	verbose("total memory used: %" PRId64 " kB", max_usage/1024);
	write_meta("memory-bytes","%" PRId64, max_usage);

	cgroup_free(&cg);
}

void cgroup_create()
{
	int ret;
	struct cgroup *cg;
	struct cgroup_controller *cg_controller;

	cg = cgroup_new_cgroup(cgroupname);
	if (!cg) {
		error(0,"cgroup_new_cgroup");
	}

	/* Set up the memory restrictions; these two options limit ram use
	   and ram+swap use. They are the same so no swapping can occur */
	cg_controller = cgroup_add_controller(cg, "memory");
	cgroup_add_value_int64(cg_controller, "memory.limit_in_bytes", memsize);
	cgroup_add_value_int64(cg_controller, "memory.memsw.limit_in_bytes", memsize);

	/* Set up cpu restrictions; we pin the task to a specific set of
	   cpus. We also give it exclusive access to those cores, and set
	   no limits on memory nodes */
	if ( cpuset!=NULL && strlen(cpuset)>0 ) {
		cg_controller = cgroup_add_controller(cg, "cpuset");
		/* To make a cpuset exclusive, some additional setup outside of domjudge is
		   required, so for now, we will leave this commented out. */
		/* cgroup_add_value_int64(cg_controller, "cpuset.cpu_exclusive", 1); */
		cgroup_add_value_string(cg_controller, "cpuset.mems", "0");
		cgroup_add_value_string(cg_controller, "cpuset.cpus", cpuset);
	} else {
		verbose("cpuset undefined");
	}

	/* Perform the actual creation of the cgroup */
	ret = cgroup_create_cgroup(cg, 1);
	if ( ret!=0 ) {
		error(0,"creating cgroup: %s(%d)", cgroup_strerror(ret), ret);
	}

	cgroup_free(&cg);
}

void cgroup_attach()
{
	int ret;
	struct cgroup *cg;

	cg = cgroup_new_cgroup(cgroupname);
	if (!cg) {
		error(0,"cgroup_new_cgroup");
	}
	ret = cgroup_get_cgroup(cg);
	if ( ret!=0 ) {
		error(0,"get cgroup information: %s(%d)", cgroup_strerror(ret), ret);
	}

	/* Attach task to the cgroup */
	ret = cgroup_attach_task(cg);
	if ( ret!=0 ) {
		error(0,"attach task to cgroup: %s(%d)", cgroup_strerror(ret), ret);
	}

	cgroup_free(&cg);
}

void cgroup_delete()
{
	int ret;
	struct cgroup *cg;

	cg = cgroup_new_cgroup(cgroupname);
	if (!cg) {
		error(0,"cgroup_new_cgroup");
	}
	ret = cgroup_get_cgroup(cg);
	if ( ret!=0 ) {
		error(0,"get cgroup information: %s(%d)", cgroup_strerror(ret), ret);
	}
	/* Clean up our cgroup */
	ret = cgroup_delete_cgroup(cg, 1);
	if ( ret!=0 ) {
		error(0,"deleting cgroup: %s(%d)", cgroup_strerror(ret), ret);
	}
	cgroup_free(&cg);
}
#endif // USE_CGROUPS

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
		walllimit_reached |= hard_timelimit;
		warning("timelimit exceeded (hard wall time): aborting command");
	} else {
		warning("received signal %d: aborting command",sig);
	}

	write_meta("signal", "%d", sig);

	/* First try to kill graciously, then hard */
	verbose("sending SIGTERM");
	if ( kill(-child_pid,SIGTERM)!=0 ) error(errno,"sending SIGTERM to command");

	/* Prefer nanosleep over sleep because of higher resolution and
	   it does not interfere with signals. */
	nanosleep(&killdelay,NULL);

	verbose("sending SIGKILL");
	if ( kill(-child_pid,SIGKILL)!=0 ) error(errno,"sending SIGKILL to command");

	/* Wait another while to make sure the process is killed by now. */
	nanosleep(&killdelay,NULL);
}

static void child_handler(int sig)
{
	received_SIGCHLD = 1;
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

long read_optarg_int(const char *desc, long minval, long maxval)
{
	long arg;
	char *ptr;

	arg = strtol(optarg,&ptr,10);
	if ( errno || *ptr!='\0' || arg<minval || arg>maxval ) {
		error(errno,"invalid %s specified: `%s'",desc,optarg);
	}

	return arg;
}

void read_optarg_time(const char *desc, double *times)
{
	char *optcopy, *ptr, *sep;

	if ( (optcopy=strdup(optarg))==NULL ) error(0,"strdup() failed");

	/* Check for soft:hard limit separator and cut string. */
	if ( (sep=strchr(optcopy,':'))!=NULL ) *sep = 0;

	times[0] = strtod(optcopy,&ptr);
	if ( errno || *ptr!='\0' || !finite(times[0]) || times[0]<=0 ) {
		error(errno,"invalid %s specified: `%s'",desc,optarg);
	}

	/* And repeat for hard limit if we found the ':' separator. */
	if ( sep!=NULL ) {
		times[1] = strtod(sep+1,&ptr);
		if ( errno || *ptr!='\0' || !finite(times[1]) || times[1]<=0 ) {
			error(errno,"invalid %s specified: `%s'",desc,optarg);
		}
		if ( times[1]<times[0] ) {
			error(0,"invalid %s specified: hard limit is lower than soft limit",desc);
		}
	} else {
		/* Set soft and hard limits equal. */
		times[1] = times[0];
	}

	free(optcopy);
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

	if ( use_cputime ) {
		/* The CPU-time resource limit can only be specified in
		   seconds, so round up: we can measure actual CPU time used
		   more accurately. Also set the real hard limit one second
		   higher: at the soft limit the kernel will send SIGXCPU at
		   the hard limit a SIGKILL. The SIGXCPU can be caught, but is
		   not by default and gives us a reliable way to detect if the
		   CPU-time limit was reached. */
		rlim_t cputime_limit = (rlim_t)ceil(cputime[1]);
		verbose("setting hard CPU-time limit to %d(+1) seconds",(int)cputime_limit);
		lim.rlim_cur = cputime_limit;
		lim.rlim_max = cputime_limit+1;
		setlim(CPU);
	}

	/* Memory limits may be handled by cgroups now */
#ifndef USE_CGROUPS
	if ( memsize!=RLIM_INFINITY ) {
		verbose("setting memory limits to %d bytes",(int)memsize);
		lim.rlim_cur = lim.rlim_max = memsize;
		setlim(AS);
		setlim(DATA);
		setlim(STACK);
	}
#else
	/* Memory limits should be unlimited when using cgroups */
	lim.rlim_cur = lim.rlim_max = RLIM_INFINITY;
	setlim(AS);
	setlim(DATA);
	setlim(STACK);
#endif

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
	if ( geteuid()==0 || getuid()==0 ) error(0,"root privileges not dropped. Do not run judgedaemon as root.");
}

int main(int argc, char **argv)
{
	sigset_t sigmask, emptymask;
	fd_set readfds;
	pid_t pid;
	int   i, r, nfds;
#ifdef USE_CGROUPS
	int   ret;
	FILE *fp;
	char *oom_path;
#endif
	int   status;
	int   exitcode;
	char *valid_users;
	char *ptr;
	int   opt;
	double tmpd;
	size_t data_passed[3];
	ssize_t nread, nwritten;

	struct itimerval itimer;
	struct sigaction sigact;

	progname = argv[0];

	/* Parse command-line options */
	use_root = use_walltime = use_cputime = use_user = no_coredump = 0;
	outputexit = outputtime = walllimit_reached = cpulimit_reached = 0;
	outputtimetype = CPU_TIME_TYPE;
	memsize = filesize = nproc = RLIM_INFINITY;
	redir_stdout = redir_stderr = limit_streamsize = 0;
	be_verbose = be_quiet = 0;
	show_help = show_version = 0;
	opterr = 0;
	while ( (opt = getopt_long(argc,argv,"+r:u:g:t:C:m:f:p:P:co:e:s:E:T:vq",long_opts,(int *) 0))!=-1 ) {
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
		case 't': /* wallclock time option */
			use_walltime = 1;
			outputtimetype = WALL_TIME_TYPE;
			read_optarg_time("walltime",walltime);
			break;
		case 'C': /* CPU time option */
			use_cputime = 1;
			outputtimetype = CPU_TIME_TYPE;
			read_optarg_time("cputime",cputime);
			break;
		case 'm': /* memsize option */
			memsize = (rlim_t) read_optarg_int("memory limit",1,LONG_MAX);
			/* Convert limit from kB to bytes and check for overflow */
			if ( memsize!=(memsize*1024)/1024 ) {
				memsize = RLIM_INFINITY;
			} else {
				memsize *= 1024;
			}
			break;
		case 'f': /* filesize option */
			filesize = (rlim_t) read_optarg_int("filesize limit",1,LONG_MAX);
			/* Convert limit from kB to bytes and check for overflow */
			if ( filesize!=(filesize*1024)/1024 ) {
				filesize = RLIM_INFINITY;
			} else {
				filesize *= 1024;
			}
			break;
		case 'p': /* nproc option */
			nproc = (rlim_t) read_optarg_int("process limit",1,LONG_MAX);
			break;
		case 'P': /* cpuset option */
			#ifdef USE_CGROUPS
				cpuset = optarg;
			#else
				error(0,"option `-P' is only supported when compiled with cgroup support.");
			#endif
			break;
		case 'c': /* no-core option */
			no_coredump = 1;
			break;
		case 'o': /* stdout option */
			redir_stdout = 1;
			stdoutfilename = strdup(optarg);
			break;
		case 'e': /* stderr option */
			redir_stderr = 1;
			stderrfilename = strdup(optarg);
			break;
		case 's': /* streamsize option */
			limit_streamsize = 1;
			streamsize = (size_t) read_optarg_int("streamsize limit",0,LONG_MAX);
			/* Convert limit from kB to bytes and check for overflow */
			if ( streamsize!=(streamsize*1024)/1024 ) {
				streamsize = (size_t) LONG_MAX;
			} else {
				streamsize *= 1024;
			}
			break;
		case 'E': /* outputexit option */
			outputexit = 1;
			exitfilename = strdup(optarg);
			break;
		case 'M': /* outputmeta option */
			outputmeta = 1;
			metafilename = strdup(optarg);
			break;
		case 'T': /* outputtime option */
			outputtime = 1;
			timefilename = strdup(optarg);
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
	if ( show_version ) version(PROGRAM,VERSION);

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

	/* Setup pipes connecting to child stdout/err streams (ignore stdin). */
	for(i=1; i<=2; i++) {
		if ( pipe(child_pipefd[i])!=0 ) error(errno,"creating pipe for fd %d",i);
	}

	if ( sigemptyset(&emptymask)!=0 ) error(errno,"creating empty signal mask");

	/* unmask all signals, except SIGCHLD: detected in pselect() below */
	sigmask = emptymask;
	if ( sigaddset(&sigmask, SIGCHLD)!=0 ) error(errno,"setting signal mask");
	if ( sigprocmask(SIG_SETMASK, &sigmask, NULL)!=0 ) {
		error(errno,"unmasking signals");
	}

	/* Construct signal handler for SIGCHLD detection in pselect(). */
	received_SIGCHLD = 0;
	sigact.sa_handler = child_handler;
	sigact.sa_flags   = 0;
	sigact.sa_mask    = emptymask;
	if ( sigaction(SIGCHLD,&sigact,NULL)!=0 ) {
		error(errno,"installing signal handler");
	}

#ifdef USE_CGROUPS
	/* Make libcgroup ready for use */
	ret = cgroup_init();
	if ( ret!=0 ) {
		error(0,"libcgroup initialization failed: %s(%d)\n", cgroup_strerror(ret), ret);
	}
	/* Define the cgroup name that we will use */
	cgroupname = (char*)malloc(256);
	if ( cgroupname==NULL ) {
		error(errno,"allocating memory for cgroupname");
	}
	/* Note: group names must have slashes! */
	snprintf(cgroupname, 256, "/domjudge/dj_cgroup_%d/", getpid());

	cgroup_create();

	unshare(CLONE_FILES|CLONE_FS|CLONE_NEWIPC|CLONE_NEWNET|CLONE_NEWNS|CLONE_NEWUTS|CLONE_SYSVSEM);

	/* Check if any Linux Out-Of-Memory killer adjustments have to
	 * be made. The oom_adj or oom_score_adj is inherited by child
	 * processes, and at least older versions of sshd seemed to set
	 * it, leading to processes getting a timelimit instead of memory
	 * exceeded, when running via SSH. */
	fp = NULL;
	if ( !fp && (fp = fopen(OOM_PATH_NEW,"r+")) ) oom_path = strdup(OOM_PATH_NEW);
	if ( !fp && (fp = fopen(OOM_PATH_OLD,"r+")) ) oom_path = strdup(OOM_PATH_OLD);
	if ( fp!=NULL ) {
		if ( fscanf(fp,"%d",&ret)!=1 ) error(errno,"cannot read from `%s'",oom_path);
		if ( ret<0 ) {
			verbose("resetting `%s' from %d to %d",oom_path,ret,OOM_RESET_VALUE);
			rewind(fp);
			if ( fprintf(fp,"%d\n",OOM_RESET_VALUE)<=0 ) {
				error(errno,"cannot write to `%s'",oom_path);
			}
		}
		if ( fclose(fp)!=0 ) error(errno,"closing file `%s'",oom_path);
	}
#endif

	switch ( child_pid = fork() ) {
	case -1: /* error */
		error(errno,"cannot fork");
	case  0: /* run controlled command */
		/* Connect pipes to command (stdin/)stdout/stderr and close unneeded fd's */
		for(i=1; i<=2; i++) {
			if ( dup2(child_pipefd[i][PIPE_IN],i)<0 ) {
				error(errno,"redirecting child fd %d",i);
			}
			if ( close(child_pipefd[i][PIPE_IN] )!=0 ||
			     close(child_pipefd[i][PIPE_OUT])!=0 ) {
				error(errno,"closing pipe for fd %d",i);
			}
		}

		/* Run the command in a separate process group so that the command
		   and all its children can be killed off with one signal. */
		if ( setsid()==-1 ) error(errno,"setsid failed");

#ifdef USE_CGROUPS
		/* Put the child process in the cgroup */
		cgroup_attach();
#endif

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

		/* Close unused file descriptors */
		for(i=1; i<=2; i++) {
			if ( close(child_pipefd[i][PIPE_IN])!=0 ) {
				error(errno,"closing pipe for fd %i",i);
			}
		}

		/* Redirect child stdout/stderr to file */
		for(i=1; i<=2; i++) {
			child_redirfd[i] = i; /* Default: no redirects */
			data_passed[i] = 0; /* Reset data counters */
		}
		if ( redir_stdout ) {
			child_redirfd[STDOUT_FILENO] = creat(stdoutfilename, S_IRUSR | S_IWUSR);
			if ( child_redirfd[STDOUT_FILENO]<0 ) {
				error(errno,"opening file '%s'",stdoutfilename);
			}
		}
		if ( redir_stderr ) {
			child_redirfd[STDERR_FILENO] = creat(stderrfilename, S_IRUSR | S_IWUSR);
			if ( child_redirfd[STDERR_FILENO]<0 ) {
				error(errno,"opening file '%s'",stderrfilename);
			}
		}

		if ( sigemptyset(&emptymask)!=0 ) error(errno,"creating empty signal mask");

		/* Construct one-time signal handler to terminate() for TERM
		   and ALRM signals. */
		sigmask = emptymask;
		if ( sigaddset(&sigmask,SIGALRM)!=0 ||
		     sigaddset(&sigmask,SIGTERM)!=0 ) error(errno,"setting signal mask");

		sigact.sa_handler = terminate;
		sigact.sa_flags   = SA_RESETHAND | SA_RESTART;
		sigact.sa_mask    = sigmask;

		/* Kill child command when we receive SIGTERM */
		if ( sigaction(SIGTERM,&sigact,NULL)!=0 ) {
			error(errno,"installing signal handler");
		}

		if ( use_walltime ) {
			/* Kill child when we receive SIGALRM */
			if ( sigaction(SIGALRM,&sigact,NULL)!=0 ) {
				error(errno,"installing signal handler");
			}

			/* Trigger SIGALRM via setitimer:  */
			itimer.it_interval.tv_sec  = 0;
			itimer.it_interval.tv_usec = 0;
			itimer.it_value.tv_sec  = (int) walltime[1];
			itimer.it_value.tv_usec = (int)(modf(walltime[1],&tmpd) * 1E6);

			if ( setitimer(ITIMER_REAL,&itimer,NULL)!=0 ) {
				error(errno,"setting timer");
			}
			verbose("setting hard wall-time limit to %.3f seconds",walltime[1]);
		}

		if ( times(&startticks)==(clock_t) -1 ) {
			error(errno,"getting start clock ticks");
		}

		/* Wait for child data or exit. */
		while ( 1 ) {

			FD_ZERO(&readfds);
			nfds = -1;
			for(i=1; i<=2; i++) {
				if ( child_pipefd[i][PIPE_OUT]>=0 ) {
					FD_SET(child_pipefd[i][PIPE_OUT],&readfds);
					nfds = max(nfds,child_pipefd[i][PIPE_OUT]);
				}
			}

			r = pselect(nfds+1, &readfds, NULL, NULL, NULL, &emptymask);
			if ( r==-1 && errno!=EINTR ) error(errno,"waiting for child data");

			if ( received_SIGCHLD ) {
				if ( (pid = wait(&status))<0 ) error(errno,"waiting on child");
				if ( pid==child_pid ) break;
			}

			/* Check to see if data is available and pass it on */
			for(i=1; i<=2; i++) {
				if ( child_pipefd[i][PIPE_OUT] != -1 && FD_ISSET(child_pipefd[i][PIPE_OUT],&readfds) ) {
					nread = read(child_pipefd[i][PIPE_OUT], buf, BUF_SIZE);
					if ( nread==-1 ) error(errno,"reading child fd %d",i);
					if ( nread==0 ) {
						/* EOF detected: close fd and indicate this with -1 */
						if ( close(child_pipefd[i][PIPE_OUT])!=0 ) {
							error(errno,"closing pipe for fd %d",i);
						}
						child_pipefd[i][PIPE_OUT] = -1;
						continue;
					}
					if ( limit_streamsize && data_passed[i]+nread>=streamsize ) {
						if ( data_passed[i]<streamsize ) {
							verbose("child fd %d limit reached",i);
						}
						nread = streamsize - data_passed[i];
					}
					nwritten = write(child_redirfd[i], buf, nread);
					if ( nwritten==-1 ) error(errno,"writing child fd %d",i);
					data_passed[i] += nwritten;
				}
			}
		}

		if ( times(&endticks)==(clock_t) -1 ) {
			error(errno,"getting end clock ticks");
		}

		if ( gettimeofday(&endtime,NULL) ) error(errno,"getting time");

		/* Test whether command has finished abnormally */
		exitcode = 0;
		if ( ! WIFEXITED(status) ) {
			if ( WIFSIGNALED(status) ) {
				if ( WTERMSIG(status)==SIGXCPU ) {
					cpulimit_reached |= hard_timelimit;
					warning("timelimit exceeded (hard cpu time)");
				} else {
					warning("command terminated with signal %d",WTERMSIG(status));
				}
				exitcode = 128+WTERMSIG(status);
			} else
			if ( WIFSTOPPED(status) ) {
				warning("command stopped with signal %d",WSTOPSIG(status));
				exitcode = 128+WSTOPSIG(status);
			} else {
				error(0,"command exit status unknown: %d",status);
			}
		} else {
			exitcode = WEXITSTATUS(status);
		}

#ifdef USE_CGROUPS
		output_cgroup_stats();
		cgroup_delete();
#endif

		/* Drop root before writing to output file(s). */
		if ( setuid(getuid())!=0 ) error(errno,"dropping root privileges");

		output_exit_time(exitcode);

		/* Return the exitstatus of the command */
		return exitcode;
	}

	/* This should never be reached */
	error(0,"unexpected end of program");
}
