# Configuration file for bash-shell scripts
# $Id$

# Root paths for various parts of the system
SYSTEM_ROOT=/home/cies/nkp0405/systeem/svn/jury
OUTPUT_ROOT=/home/cies/nkp0405/systeem/systest
INPUT_ROOT=/home/cies/nkp0405/opgaven

# Paths within OUTPUT_ROOT
INCOMINGDIR=$OUTPUT_ROOT/incoming
SUBMITDIR=$OUTPUT_ROOT/sources
JUDGEDIR=$OUTPUT_ROOT/judging

# Maximum seconds available for compiling
COMPILETIME=30

# Maximum size of solution source code in KB
SOURCESIZE=256

# Maximum memory usage by solutions in KB (including bash-shell)
MEMLIMIT=65536
	 
# User under which to run solutions (can be ID or name)
RUNUSER="nobody"
