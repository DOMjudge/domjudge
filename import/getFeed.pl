#!/usr/bin/perl -w

use strict;

unlink('tmp.xml');
my $tmp = "";
while ( <STDIN> ) {
	my $line = $_;
	$tmp .= $line;

	if ( $line =~ /.*<\/run>.*/ 
		|| $line =~ /.*<\/team>.*/ 
		|| $line =~ /.*<\/clar>.*/ 
		) {
		open(HANDLE, ">>tmp.xml");
		print HANDLE $tmp;
		close(HANDLE);
		`cp tmp.xml intermediate.xml`;
		`echo "</contest>" >> intermediate.xml`;
		`cp intermediate.xml testfeed.xml`;
		$tmp = "";	
	}
}
