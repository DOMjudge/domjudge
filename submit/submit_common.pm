# Common functions and variables for perl submit-scripts
# Include in scripts with 'require "<filename>";'
package submit_common;

# Export all variable definitions to user namespace
require Exporter;
@ISA = qw(Exporter);
@EXPORT = qw($success $failure $socket $lastreply netchomp sendit receive);

# For extra clarity in return statements
$success = 1;
$failure = 0;

# Define variables for client-server communication
my $socket;
my $lastreply;

# Common functions for sending/receiving data over tcp/ip
sub netchomp {
	s/\015\012//;
}

sub sendit { # 'send' is already defined
	logmsg("send: @_");
	print $socket "@_\015\012";
}

sub receive {
	if ( ! ($_ = <$socket>) ) { return $failure; }
	netchomp;
	if ( /^[^+]/ ) { error("received: $_\n"); }
	logmsg("recv: $_");
	s/^.//;
	$lastreply = $_;
	return $success;
}

# End of configuration file: end with true (needed by 'require')
1;
