/*
 * Functions for reading runtime configuration files
 *
 * $Id$
 */

#ifndef __LIB_CONFIG_H
#define __LIB_CONFIG_H

#include <stdlib.h>

#ifdef __cplusplus
extern "C" {
#endif

#define CONFIG_MAXLEN 255

typedef struct {
	char name[CONFIG_MAXLEN+1];
	char value[CONFIG_MAXLEN+1];
} config_option;

void  config_init();
int   config_isset(const char *);
char *config_getvalue(const char *);
void  config_setvalue(const char *, const char *);
int   config_readfile(const char *);

#ifdef __cplusplus
}
#endif

#endif // __LIB_CONFIG_H
