/*
 * tempfile -- utility to savely create temporary files
 *
 * Copied from the Debian GNU/Linux package debianutils.
 *
 * This code may distributed under the terms of the GNU Public License
 * which can be found at http://www.gnu.org/copyleft or in the file
 * COPYING supplied with this code.
 *
 */

#include <stdio.h>
#include <unistd.h>
#include <stdlib.h>
#include <getopt.h>
#include <errno.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>
#include <string.h>

char *progname;

void
usage (int status)
{
  if (status)
    fprintf(stderr, "Try `%s --help' for more information.\n", progname);
  else
    printf("Usage: %s [OPTION]\n\
Create a temporary file in a safe manner.
\n\
-d, --directory=DIR  place temporary file in DIR\n\
-p, --prefix=STRING  set temporary file's prefix to STRING\n\
-s, --suffix=STRING  set temporary file's suffix to STRING\n\
-m, --mode=MODE      open with MODE instead of 0600\n\
-n, --name=FILE      use FILE instead of tempnam(3)\n\
    --help           display this help and exit\n\
    --version        output version information and exit\n", progname);
  exit(status);
}


void
syserror (const char *fx)
{
  perror(fx);
  exit(1);
}


int
parsemode (const char *in, mode_t *out)
{
  char *endptr;
  long int mode;
  mode = strtol(in, &endptr, 8);
  if (*endptr || mode<0 || mode>07777)
    return 1;
  *out = (mode_t) mode;
  return 0;
}


int
main (int argc, char **argv)
{
  char *name=0, *dir=0, *pfx=0, *sfx=0;
  mode_t mode = 0600;
  int fd, optc;
  struct option long_options[] = {
    {"prefix", required_argument, 0, 'p'},
    {"suffix", required_argument, 0, 's'},
    {"directory", required_argument, 0, 'd'},
    {"mode", required_argument, 0, 'm'},
    {"name", required_argument, 0, 'n'},
    {"help", no_argument, 0, 'h'},
    {"version", no_argument, 0, 'v'},
    {0, 0, 0, 0}
  };
  progname = argv[0];

  while ((optc = getopt_long (argc, argv, "p:s:d:m:n:", long_options, 0))
	 != EOF) {
    switch (optc) {
    case 0:
      break;
    case 'p':
      pfx = optarg;
      break;
    case 's':
      sfx = optarg;
      break;
    case 'd':
      dir = optarg;
      break;
    case 'm':
      if (parsemode(optarg, &mode)) {
	fprintf(stderr, "Invalid mode `%s'.  Mode must be octal.\n", optarg);
	usage(1);
      }
      break;
    case 'n':
      name = optarg;
      break;
    case 'h':
      usage(0);
    case 'v':
      puts("tempfile 1.0");
      exit(0);
    default:
      usage(1);
    }
  }

  if (name) {
    if ((fd = open(name, O_RDWR | O_CREAT | O_EXCL, mode)) < 0)
      syserror("open");
  }

  else {
    for (;;) {
      if (!(name = tempnam(dir, pfx)))
	syserror("tempnam");
      if ((fd = open(name, O_RDWR | O_CREAT | O_EXCL, mode)) < 0) {
	if (errno == EEXIST) {
	  free(name);
	  continue;
	}
	syserror("open");
      }
      if (sfx) {
	char *namesfx;
	if (!(namesfx = malloc(strlen(name) + strlen(sfx) + 1)))
	  syserror("malloc");
	strcpy(namesfx, name);
	strcat(namesfx, sfx);
	if (link(name, namesfx) < 0) {
	  if (errno == EEXIST) {
	    if (unlink(name) < 0)
	      syserror("unlink");
	    free(name);
	    free(namesfx);
	    continue;
	  }
	  syserror("link");
	}
	if (unlink(name) < 0)
	  syserror("unlink");
	free(name);
	name = namesfx;
      }
      break;
    }
  }
  
  if (close(fd))
    syserror("close");
  puts(name);
  exit(0);
}
