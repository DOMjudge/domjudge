#ifndef _LOCAL_CONFIG_
#define _LOCAL_CONFIG_

#include "domserver-static.h"

/* FIXME: "Configuration" below is also needed by submit client and
   therefore not easily converted into runtime config options. */
 
/* Define the command to copy files to the user submit directory in the
   submit client differently under Windows and Linux */
#if defined(__CYGWIN__) || defined(__CYGWIN32__)
#define COPY_CMD "c:/cygwin/bin/cp"
#else
#define COPY_CMD "cp"
#endif

#define SUBMITPORT   9147
#define USERDIR      ".domjudge"
#define USERPERMDIR  0700
#define USERPERMFILE 0600

#define WARN_MTIME   5
#define SOURCESIZE   256
#define LANG_EXTS    "C,c C++,cpp,cc,c++ Java,java Pascal,pas,p Haskell,hs,lhs Perl,pl Bash,sh"

/* End of config required by submit client */

// This stuff should be removed when feature request #1878083 is solved:

#define BEEP_ERROR   "-l 1000 -d 500 -f 800 -n -l 1000 -d 500 -f 1000 -n "\
                     "-l 1000 -d 500 -f 800 -n -l 1000 -d 500 -f 1000 -n "\
                     "-l 1000 -d 500 -f 800 -n -l 1000 -d 500 -f 1000 -n "\
                     "-l 1000 -d 500 -f 800 -n -l 1000 -d 500 -f 1000 -n "\
                     "-l 1000 -d 500 -f 800 -n -l 1000 -d 500 -f 1000"
#define BEEP_WARNING "-l 1000 -d 500 -f 300 -n -l 1000 -d 500 -f 200 -n "\
                     "-l 1000 -d 500 -f 300 -n -l 1000 -d 500 -f 200"
#define BEEP_SUBMIT  "-f 400 -l 100 -n -f 400 -l 70"
#define BEEP_ACCEPT  "-f 400 -l 100 -n -f 500 -l 70"
#define BEEP_REJECT  "-f 400 -l 100 -n -f 350 -l 70"

#endif /* _LOCAL_CONFIG_ */
