# Configuration file for bash-shell scripts
# $Id$

# Root paths for various parts of the system
SYSTEM_ROOT=$HOME/systeem/svn/jury
OUTPUT_ROOT=$HOME/systeem/systest
INPUT_ROOT=$HOME/opgaven

# Paths within OUTPUT_ROOT
INCOMINGDIR=$OUTPUT_ROOT/incoming
SUBMITDIR=$OUTPUT_ROOT/sources
JUDGEDIR=$OUTPUT_ROOT/judging
LOGDIR=$OUTPUT_ROOT/log

# Loglevels (as defined in syslog.h)
LOG_EMERG=0
LOG_ALERT=1
LOG_CRIT=2
LOG_ERR=3
LOG_WARNING=4
LOG_NOTICE=5
LOG_INFO=6
LOG_DEBUG=7

# User under which to run solutions (can be ID or name)
RUNUSER="test"

# Maximum seconds available for compiling
COMPILETIME=30

# Maximum size of solution source code in KB
SOURCESIZE=256

# Maximum memory usage by RUNUSER in KB (including bash-shell)
MEMLIMIT=65536

# Maximum filesize RUNUSER may write in KB
FILELIMIT=4096

# Maximum no. processes running as RUNUSER
PROCLIMIT=8
