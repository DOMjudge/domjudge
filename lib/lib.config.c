/*
 * Functions for reading runtime configuration files
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <ctype.h>

#include "lib.error.h"
#include "lib.config.h"

config_option *options = NULL;
size_t noptions = 0, options_size = 0;

void config_init()
{
	options = NULL;
	noptions = 0;
	options_size = 0;
}

int config_getindex(const char *option)
{
	int i;
	for(i=0; i<noptions; i++) {
		if ( strcmp(options[i].name,option)==0 ) return i;
	}
	return -1;
}

int config_newindex()
{
	if ( noptions<options_size ) return noptions++;

	// dynamically resize exponentially
	options_size = 2*options_size+1;

	options = (config_option *) realloc((void *)options,options_size*sizeof(config_option));
	if ( options==NULL ) abort();

	return noptions++;
}

int config_isset(const char *option)
{
	return config_getindex(option)>=0;
}

char *config_getvalue(const char *option)
{
	int i = config_getindex(option);
	if ( i<0 ) return NULL;
	return options[i].value;
}

void config_setvalue(const char *option, const char *value)
{
	int i = config_getindex(option);
	if ( i<0 ) i = config_newindex();
	
	strncpy(options[i].name, option, CONFIG_MAXLEN);
	strncpy(options[i].value, value, CONFIG_MAXLEN);
}

int config_readfile(const char *filename)
{
	char line[2*CONFIG_MAXLEN+10];
	char *option, *value, *tmp;
	FILE *in;
	int i, lineno = 0;

	if ( (in = fopen(filename,"rt"))==NULL ) error(errno,"opening '%s'",filename);

	// read line by line
	while ( fgets(line,2*CONFIG_MAXLEN+8,in)!=NULL ) {
		lineno++;
		
		// Search first non-whitespace char
		option = line;
		while ( isspace(*option) ) option++;

		if ( *option==0 ) continue; // empty line
		if ( *option==';' || *option=='#' ) continue; // comment line

		// Search first '=': key/value separator
		if ( (tmp = strchr(line,'='))==NULL ) {
			error(0,"on line %d of '%s': no key/value pair found",lineno,filename);
		}
		*tmp = 0;
		value = tmp + 1;
		while ( isspace(*value) ) value++;

		// Trim trailing whitespace from option, value
		tmp = option + strlen(option)-1;
		while ( isspace(*(tmp)) ) *(tmp--) = 0;

		tmp = value + strlen(value)-1;
		while ( isspace(*(tmp)) ) *(tmp--) = 0;

		// Check option for alphanumeric chars and _
		if ( strlen(option)==0 ) error(0,"on line %d of '%s': empty key",lineno,filename);
		for(i=0; i<strlen(option); i++) {
			if ( !(isalnum(option[i]) || option[i]=='_') ) {
				error(0,"on line %d of '%s': illegal char in key '%s'",
				      lineno,filename,option);
			}
		}

		// Strip optional enclosing quotes from value
		if ( strlen(value)>=2 && *value=='"' && *(value+strlen(value)-1)=='"' ) {
			value++;
			*(value+strlen(value)-1) = 0;
		}

		config_setvalue(option,value);
	}

	fclose(in);

	return 0;
}
