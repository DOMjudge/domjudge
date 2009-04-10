#ifndef _SUBMIT_CONFIG_
#define _SUBMIT_CONFIG_

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

#endif /* _SUBMIT_CONFIG_ */
