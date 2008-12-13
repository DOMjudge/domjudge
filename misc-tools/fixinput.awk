#!/usr/bin/awk -f
# Usage: awk -f fixinput.awk [file ...]
NF == 0 { next } # skip (don't print) lines with whitespace only
{
	gsub(/\t/, " ") # change tabs to spaces
	gsub(/ +/, " ") # change multiple spaces to a single space
	gsub(/^ /, "")  # remove leading spaces
	gsub(/ $/, "")  # remove trailing spaces
	gsub(/[^[:print:]]/, "") # remove unprintable characters
	print # print result (automatically adds missing eol to last line)
}
