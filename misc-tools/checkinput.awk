#!/usr/bin/awk -f
# Usage: awk -f checkinput.awk [file ...]
BEGIN { max = 1048578; RT = RS }

length() > max { myprint("Longer than "max" characters") }
/^$/ { myprint("Empty line") }
/^ / { myprint("Starts with whitespace") }
/ $/ { myprint("End with whitespace") }
/\t/ { myprint("Tab character") }
/  / { myprint("Double whitespace") }
/[^[:print:]]/ { myprint("Unprintable character(s)") }
RT != RS { myprint("No end of line") } # only works in gawk

function myprint(str) {
	printf "%s, line %3d: %s.\n", FILENAME, FNR, str
}
