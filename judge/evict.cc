#include "config.h"

#include <iostream>
#include <string>
#include <vector>

#include <dirent.h>
#include <fcntl.h>
#include <getopt.h>
#include <unistd.h>
#include <sys/stat.h>
#include <cstring>
#include <cstdlib>

#include "lib.misc.h"

extern "C" {
#include "lib.error.h"
}

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
    std::cout << "Usage: " << progname << " [OPTION]... DIRECTORY" << std::endl
              << "Evicts all files in a directory tree from the kernel filesystem cache." << std::endl << std::endl
              << "  -v, --verbose        display some extra warnings and information" << std::endl
              << "      --help           display this help and exit" << std::endl
              << "      --version        output version information and exit" << std::endl << std::endl;
	exit(0);
}

void evict_directory(const std::string& dirname) {
	DIR *dir;
	struct dirent *entry;
	int fd = -1;
	struct stat s;

	dir = opendir(dirname.c_str());
	if (dir != NULL) {
		if (be_verbose) logmsg(LOG_INFO, "Evicting all files in directory: %s", dirname.c_str());

		/* Read everything in the directory */
		while ( (entry = readdir(dir)) != NULL ) {
			/* skip over current/parent directory entries */
			if (strcmp(entry->d_name, ".") == 0 || strcmp(entry->d_name, "..") == 0) {
				continue;
			}

			/* Construct the full file path */
			std::string entry_path = dirname + "/" + entry->d_name;
			fd = open(entry_path.c_str(), O_RDONLY, 0);
			if (fd == -1) {
				warning(errno, "Unable to open file: %s", entry_path.c_str());
				continue;
			}

			if (fstat(fd, &s) < 0) {
				if (be_verbose) logerror(errno, "Unable to stat file/directory: %s\n", entry_path.c_str());
				if ( close(fd)!=0 ) {
					warning(errno, "Unable to close file: %s", entry_path.c_str());
				}
				continue;
			}
			if (S_ISDIR(s.st_mode)) {
				/* Recurse into subdirectories */
				evict_directory(entry_path);
			} else {
				/* evict this file from the cache */
				if (posix_fadvise(fd, 0, 0, POSIX_FADV_DONTNEED)) {
					warning(errno, "Unable to evict file: %s\n", entry_path.c_str());
				} else {
					if (be_verbose) logmsg(LOG_DEBUG, "Evicted file: %s", entry_path.c_str());
				}
			}

			if ( close(fd)!=0 ) {
				warning(errno, "Unable to close file: %s", entry_path.c_str());
			}
		}
		if ( closedir(dir)!=0 ) {
			warning(errno, "Unable to close directory: %s", dirname.c_str());
		}
	} else {
		warning(errno, "Unable to open directory: %s", dirname.c_str());
	}
}

int main(int argc, char *argv[])
{
	int opt;

	progname = argv[0];

	/* Parse command-line options */
	be_verbose = show_help = show_version = 0;
	opterr = 0;
	while ( (opt = getopt_long(argc,argv,"+v",long_opts,(int *) 0))!=-1 ) {
		switch ( opt ) {
		case 0:   /* long-only option */
			break;
		case 'v': /* verbose option */
			be_verbose = 1;
			verbose = LOG_DEBUG;
			break;
		case ':': /* getopt error */
		case '?':
			logmsg(LOG_ERR, "unknown option or missing argument `%c'", optopt);
			break;
		default:
			logmsg(LOG_ERR, "getopt returned character code `%c' ??", (char)opt);
		}
	}

	if ( show_help ) usage();
	if ( show_version ) version(PROGRAM,VERSION);

	if ( argc<=optind ) {
		logmsg(LOG_ERR, "no directory specified");
		return 1;
	}

	/* directory to evict */
	std::string dirname = argv[optind];

	evict_directory(dirname);

	return 0;
}
