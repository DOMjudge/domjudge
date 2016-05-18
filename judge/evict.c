#include "config.h"

#include <stdio.h>
#include <stdarg.h>
#include <dirent.h>
#include <sys/stat.h>
#include <string.h>
#include <fcntl.h>
#include <getopt.h>
#include <unistd.h>
#include <malloc.h>
#include <stdlib.h>


#include "lib.error.h"
#include "lib.misc.h"

#define PROGRAM "evict"
#define VERSION DOMJUDGE_VERSION "/" REVISION

extern int errno;

const char *progname;

int be_verbose;
int show_help;
int show_version;

struct option const long_opts[] = {
	{"verbose", no_argument,       NULL,         'v'},
	{"help",    no_argument,       &show_help,    1 },
	{"version", no_argument,       &show_version, 1 },
	{ NULL,     0,                 NULL,          0 }
};

void usage()
{
	printf("\
Usage: %s [OPTION]... DIRECTORY\n\
Evicts all files in a directory from the kernel filesystem cache\n\
\n\
  -v, --verbose        display some extra warnings and information\n\
      --help           display this help and exit\n\
      --version        output version information and exit\n\
\n", progname);
	exit(0);
}

void evict_directory(char *dirname) {
	DIR *dir;
	struct dirent *entry;
	int fd;
	char *entry_path;
	struct stat s;


	dir = opendir(dirname);
	if (dir != NULL) {
		if (be_verbose) warning(0, "Evicting all files in directory: %s", dirname);

		/* Read everything in the directory */
		while ( (entry = readdir(dir)) != NULL ) {
			/* skip over current/parent directory entries */
			if (strcmp(entry->d_name, ".") == 0 || strcmp(entry->d_name, "..") == 0) {
				continue;
			}

			/* Construct the full file path */
			entry_path = allocstr("%s/%s", dirname, entry->d_name);

			if (stat(entry_path, &s) < 0) {
				if (be_verbose) logerror(errno, "Unable to stat file/directory: %s\n", entry_path);
				free(entry_path);
				continue;
			}
			if (S_ISDIR(s.st_mode)) {
				/* Recurse into subdirectories */
				evict_directory(entry_path);
			} else {
				/* evict this file from the cache */
				fd = open(entry_path, O_RDONLY, 0);
				if (fd == -1) {
					warning(errno, "Unable to open file: %s", entry_path);
				} else {
					if (posix_fadvise(fd, 0, 0, POSIX_FADV_DONTNEED)) {
						warning(0, "Unable to evict file: %s\n", entry_path);
					}
				}
				close(fd);
			}
			free(entry_path);
		}
		closedir(dir);
	} else {
		warning(0, "Unable to open directory: %s", dirname);
	}
}

int main(int argc, char *argv[])
{
	int opt;
	char* dirname;

	progname = argv[0];

	/* Parse command-line options */
	be_verbose = show_help = show_version = 0;
	opterr = 0;
	while ( (opt = getopt_long(argc,argv,"+",long_opts,(int *) 0))!=-1 ) {
		switch ( opt ) {
		case 0:   /* long-only option */
			break;
		case 'v': /* verbose option */
			be_verbose = 1;
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

	if ( argc<=optind ) error(0,"no directory specified");

	/* directory to evict */
	dirname = argv[optind];

	evict_directory(dirname);

	return 0;
}
