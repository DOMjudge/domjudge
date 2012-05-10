#!/usr/bin/perl -w

use WWW::Mechanize;
use File::Path qw(make_path);
use strict;

# config section
my $SERVER = "https://contest.dev.scrool.se/";
my $USER = "test";
my $PASSWORD = "testingtester";
my $DIRECTORY = "submissions";

make_path($DIRECTORY);

# login into Kattis
my $mech = WWW::Mechanize->new();
my $response = $mech->get($SERVER . "login?show_pw_login=true");
if ($response->is_error) {
	die("login page not found");
}
$response = $mech->submit_form(
	form_number => 1,
	fields      => {
		user     => $USER,
		password => $PASSWORD,
	}
);
if ($response->is_error || $response->decoded_content =~ /Error: Unknown Username\/Password/) {
	die("login failed, check user/password\n");
}

print STDERR "waiting for submission requests\n";

# read requested submissions from stdin
while (my $submID = <STDIN>) {
	chomp($submID);

	my $filename = $DIRECTORY . "/" . $submID . ".zip";
	if (-r $filename) {
		print STDERR "submission $submID already downloaded ($filename)\n";
	} else {
		print STDERR "downloading submission $submID\n";

		# download and store submission data
		$response = $mech->get($SERVER . "download/submissiondata?id=" . $submID . "&allfiles=1");
		if ($response->is_error) {
			print STDERR "problem while downloading submission " . $submID . ": " . $response->status_line . "\n";
		} elsif ($response->decoded_content =~ /^No such submission\.$/) {
			print STDERR "submission $submID not found\n";
		} else {
			open(ZIPFILE, ">" . $filename);
			print ZIPFILE $response->decoded_content;
			close(ZIPFILE);
		}
	}
}
