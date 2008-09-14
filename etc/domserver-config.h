#ifndef _LOCAL_CONFIG_
#define _LOCAL_CONFIG_

#include "domserver-static.h"

#define SUBMITPORT   9147
#define USERDIR      ".domjudge"
#define USERPERMDIR  0700
#define USERPERMFILE 0600
#define BEEP_CMD     BINDIR"/beep"

#define WARN_MTIME   5
#define SOURCESIZE   256
#define LANG_EXTS    "C,c C++,cpp,cc,c++ Java,java Pascal,pas,p Haskell,hs,lhs Perl,pl Bash,sh"

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
