/* Configuration file for C/C++ programs
 * $Id$
 */

/*** AUTOGENERATE HEADER START ***/

// This is a template configuration file.
//
// Use `generate_config.sh' to generate the corresponding config file
// from the global config file.

/*** AUTOGENERATE HEADER END ***/

#ifndef _LOCAL_CONFIG_
#define _LOCAL_CONFIG_

/* Define the command to copy files to the user submit directory in the
   submit client differently under Windows and Linux */
#if defined(__CYGWIN__) || defined(__CYGWIN32__)
#define COPY_CMD "c:/cygwin/bin/cp"
#else
#define COPY_CMD "cp"
#endif

/*** GLOBAL CONFIG INCLUDE START ***/
/*** GLOBAL CONFIG INCLUDE END ***/

#endif /* _LOCAL_CONFIG_ */
