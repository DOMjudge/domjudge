#!/usr/bin/perl -w
# $Id$

# Simple script to use the mkstemps function of Perl.

use strict;
use File::Temp;

my $template;
my $suffix;
my $filehandle;
my $tempfile;

if ( @ARGV != 2 ) { die "$0: invalid arguments"; }
$template = $ARGV[0];
$suffix   = $ARGV[1];

($filehandle, $tempfile) = mkstemps($template,$suffix)
	or die "$0: creating tempfile: $!";

print $tempfile;
