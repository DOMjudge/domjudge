/*  beep - just what it sounds like, makes the console beep - but with
 * precision control.  See the man page for details.
 *
 * Try beep -h for command line args
 *
 * This code is copyright (C) Johnathan Nightingale, 2000.
 *
 * This code may distributed only under the terms of the GNU Public License 
 * which can be found at http://www.gnu.org/copyleft or in the file COPYING 
 * supplied with this code.
 *
 * This code is not distributed with warranties of any kind, including implied
 * warranties of merchantability or fitness for a particular use or ability to 
 * breed pandas in captivity, it just can't be done.
 *
 * Bug me, I like it:  http://johnath.com/  or johnath@johnath.com
 */

#include <fcntl.h>
#include <getopt.h>
#include <signal.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <sys/ioctl.h>
#include <sys/types.h>
#include <linux/kd.h>

/* I don't know where this number comes from, I admit that freely.  A 
   wonderful human named Raine M. Ekman used it in a program that played
   a tune at the console, and apparently, it's how the kernel likes its
   sound requests to be phrased.  If you see Raine, thank him for me.  
   
   June 28, email from Peter Tirsek (peter at tirsek dot com):
   
   This number represents the fixed frequency of the original PC XT's
   timer chip (the 8254 AFAIR), which is approximately 1.193 MHz. This
   number is divided with the desired frequency to obtain a counter value,
   that is subsequently fed into the timer chip, tied to the PC speaker.
   The chip decreases this counter at every tick (1.193 MHz) and when it
   reaches zero, it toggles the state of the speaker (on/off, or in/out),
   resets the counter to the original value, and starts over. The end
   result of this is a tone at approximately the desired frequency. :)
*/
#ifndef CLOCK_TICK_RATE
#define CLOCK_TICK_RATE 1193180
#endif

#define VERSION_STRING "beep-1.2.2"
char *copyright = 
"Copyright (C) Johnathan Nightingale, 2002.  "
"Use and Distribution subject to GPL.  "
"For information: http://www.gnu.org/copyleft/.";

/* Meaningful Defaults */
#define DEFAULT_FREQ       440.0 /* Middle A */
#define DEFAULT_LENGTH     200   /* milliseconds */
#define DEFAULT_REPS       1
#define DEFAULT_DELAY      100   /* milliseconds */
#define DEFAULT_END_DELAY  NO_END_DELAY
#define DEFAULT_STDIN_BEEP NO_STDIN_BEEP

/* Other Constants */
#define NO_END_DELAY    0
#define YES_END_DELAY   1

#define NO_STDIN_BEEP   0
#define LINE_STDIN_BEEP 1
#define CHAR_STDIN_BEEP 2

typedef struct beep_parms_t {
  float freq;     /* tone frequency (Hz)      */
  int length;     /* tone length    (ms)      */
  int reps;       /* # of repetitions         */
  int delay;      /* delay between reps  (ms) */
  int end_delay;  /* do we delay after last rep? */
  int stdin_beep; /* are we using stdin triggers?  We have three options:
		     - just beep and terminate (default)
		     - beep after a line of input
		     - beep after a character of input
		     In the latter two cases, pass the text back out again,
		     so that beep can be tucked appropriately into a text-
		     processing pipe.
		  */
  struct beep_parms_t *next;  /* in case -n/--new is used. */
} beep_parms_t;

/* Momma taught me never to use globals, but we need something the signal 
   handlers can get at.*/
int console_fd = -1;

/* If we get interrupted, it would be nice to not leave the speaker beeping in
   perpetuity. */
void handle_signal(int signum) {
  switch(signum) {
  case SIGINT:
    if(console_fd >= 0) {
      /* Kill the sound, quit gracefully */
      ioctl(console_fd, KIOCSOUND, 0);
      close(console_fd);
      exit(signum);
    } else {
      /* Just quit gracefully */
      exit(signum);
    }
  }
}

/* print usage and exit */
void usage_bail(const char *executable_name) {
  printf("Usage:\n%s [-f freq] [-l length] [-r reps] [-d delay] "
	 "[-D delay] [-s] [-c]\n",
	 executable_name);
  printf("%s [Options...] [-n] [--new] [Options...] ... \n", executable_name);
  printf("%s [-h] [--help]\n", executable_name);
  printf("%s [-v] [-V] [--version]\n", executable_name);
  exit(1);
}


/* Parse the command line.  argv should be untampered, as passed to main.
 * Beep parameters returned in result, subsequent parameters in argv will over-
 * ride previous ones.
 * 
 * Currently valid parameters:
 *  "-f <frequency in Hz>"
 *  "-l <tone length in ms>"
 *  "-r <repetitions>"
 *  "-d <delay in ms>"
 *  "-D <delay in ms>" (similar to -d, but delay after last repetition as well)
 *  "-s" (beep after each line of input from stdin, echo line to stdout)
 *  "-c" (beep after each char of input from stdin, echo char to stdout)
 *  "-h/--help"
 *  "-v/-V/--version"
 *  "-n/--new"
 *
 * March 29, 2002 - Daniel Eisenbud points out that c should be int, not char,
 * for correctness on platforms with unsigned chars.
 */
void parse_command_line(int argc, char **argv, beep_parms_t *result) {
  int c;

  struct option opt_list[4] = {{"help", 0, NULL, 'h'},
			       {"version", 0, NULL, 'V'},
			       {"new", 0, NULL, 'n'},
			       {0,0,0,0}};
  while((c = getopt_long(argc, argv, "f:l:r:d:D:schvVn", opt_list, NULL))
	!= EOF) {
    int argval = -1;    /* handle parsed numbers for various arguments */
    float argfreq = -1; 
    switch(c) {      
    case 'f':  /* freq */
      if(!sscanf(optarg, "%f", &argfreq) || (argfreq >= 20000 /* ack! */) || 
	 (argfreq <= 0))
	usage_bail(argv[0]);
      else
	result->freq = argfreq;    
      break;
    case 'l' : /* length */
      if(!sscanf(optarg, "%d", &argval) || (argval < 0))
	usage_bail(argv[0]);
      else
	result->length = argval;
      break;
    case 'r' : /* repetitions */
      if(!sscanf(optarg, "%d", &argval) || (argval < 0))
	usage_bail(argv[0]);
      else
	result->reps = argval;
      break;
    case 'd' : /* delay between reps - WITHOUT delay after last beep*/
      if(!sscanf(optarg, "%d", &argval) || (argval < 0))
	usage_bail(argv[0]);
      else {
	result->delay = argval;
	result->end_delay = NO_END_DELAY;
      }
      break;
    case 'D' : /* delay between reps - WITH delay after last beep */
      if(!sscanf(optarg, "%d", &argval) || (argval < 0))
	usage_bail(argv[0]);
      else {
	result->delay = argval;
	result->end_delay = YES_END_DELAY;
      }
      break;
    case 's' :
      result->stdin_beep = LINE_STDIN_BEEP;
      break;
    case 'c' :
      result->stdin_beep = CHAR_STDIN_BEEP;
      break;
    case 'v' :
    case 'V' : /* also --version */
      printf("%s\n",VERSION_STRING);
      exit(0);
      break;
    case 'n' : /* also --new - create another beep */
      result->next = (beep_parms_t *)malloc(sizeof(beep_parms_t));
      result->next->freq       = DEFAULT_FREQ;
      result->next->length     = DEFAULT_LENGTH;
      result->next->reps       = DEFAULT_REPS;
      result->next->delay      = DEFAULT_DELAY;
      result->next->end_delay  = DEFAULT_END_DELAY;
      result->next->stdin_beep = DEFAULT_STDIN_BEEP;
      result->next->next       = NULL;
      result = result->next; /* yes, I meant to do that. */
      break;
    case 'h' : /* notice that this is also --help */
    default :
      usage_bail(argv[0]);
    }
  }
}  

void play_beep(beep_parms_t parms) {
  int i; /* loop counter */

  /* try to snag the console */
  if((console_fd = open("/dev/console", O_WRONLY)) == -1) {
    fprintf(stderr, "Could not open /dev/console for writing.\n");
    printf("\a");  /* Output the only beep we can, in an effort to fall back on usefulness */
    perror("open");
    exit(1);
  }
  
  /* Beep */
  for (i = 0; i < parms.reps; i++) {                    /* start beep */
    if(ioctl(console_fd, KIOCSOUND, (int)(CLOCK_TICK_RATE/parms.freq)) < 0) {
      printf("\a");  /* Output the only beep we can, in an effort to fall back on usefulness */
      perror("ioctl");
    }
    /* Look ma, I'm not ansi C compatible! */
    usleep(1000*parms.length);                          /* wait...    */
    ioctl(console_fd, KIOCSOUND, 0);                    /* stop beep  */
    if(parms.end_delay || (i+1 < parms.reps))
       usleep(1000*parms.delay);                        /* wait...    */
  }                                                     /* repeat.    */

  close(console_fd);
}



int main(int argc, char **argv) {
  char sin[4096], *ptr;
  
  beep_parms_t *parms = (beep_parms_t *)malloc(sizeof(beep_parms_t));
  parms->freq       = DEFAULT_FREQ;
  parms->length     = DEFAULT_LENGTH;
  parms->reps       = DEFAULT_REPS;
  parms->delay      = DEFAULT_DELAY;
  parms->end_delay  = DEFAULT_END_DELAY;
  parms->stdin_beep = DEFAULT_STDIN_BEEP;
  parms->next       = NULL;

  signal(SIGINT, handle_signal);
  parse_command_line(argc, argv, parms);

  /* this outermost while loop handles the possibility that -n/--new has been
     used, i.e. that we have multiple beeps specified. Each iteration will
     play, then free() one parms instance. */
  while(parms) {
    beep_parms_t *next = parms->next;

    if(parms->stdin_beep) {
      /* in this case, beep is probably part of a pipe, in which case POSIX 
	 says stdin and out should be fuly buffered.  This however means very 
	 laggy performance with beep just twiddling it's thumbs until a buffer
	 fills. Thus, kill the buffering.  In some situations, this too won't 
	 be enough, namely if we're in the middle of a long pipe, and the 
	 processes feeding us stdin are buffered, we'll have to wait for them,
	 not much to  be done about that. */
      setvbuf(stdin, NULL, _IONBF, 0);
      setvbuf(stdout, NULL, _IONBF, 0);
      while(fgets(sin, 4096, stdin)) {
	if(parms->stdin_beep==CHAR_STDIN_BEEP) {
	  for(ptr=sin;*ptr;ptr++) {
	    putchar(*ptr);
	    fflush(stdout);
	    play_beep(*parms);
	  }
	} else {
	  fputs(sin, stdout);
	  play_beep(*parms);
	}
      }
    } else {
      play_beep(*parms);
    }

    /* Junk each parms struct after playing it */
    free(parms);
    parms = next;
  }

  return EXIT_SUCCESS;
}
