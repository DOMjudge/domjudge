// SPDX-License-Identifier: LGPL-2.1-only
/**
 * Copyright Red Hat, Inc. 2012
 *
 * Author:	Jan Safranek <jsafrane@redhat.com>
 */

#include <libcgroup.h>
#include <libcgroup-internal.h>

#include <strings.h>
#include <stdarg.h>
#include <stdlib.h>
#include <errno.h>
#include <stdio.h>

static cgroup_logger_callback cgroup_logger;
static void *cgroup_logger_userdata;
static int cgroup_loglevel;

static void cgroup_default_logger(void *userdata, int level, const char *fmt,
				  va_list ap)
{
	vfprintf(stdout, fmt, ap);
}

void cgroup_log(int level, const char *fmt, ...)
{
	va_list ap;

	if (!cgroup_logger)
		return;

	if (level > cgroup_loglevel)
		return;

	va_start(ap, fmt);
	cgroup_logger(cgroup_logger_userdata, level, fmt, ap);
	va_end(ap);
}

void cgroup_set_logger(cgroup_logger_callback logger, int loglevel,
		       void *userdata)
{
	cgroup_logger = logger;
	cgroup_set_loglevel(loglevel);
	cgroup_logger_userdata = userdata;
}

void cgroup_set_default_logger(int level)
{
	if (!cgroup_logger)
		cgroup_set_logger(cgroup_default_logger, level, NULL);
}

int cgroup_parse_log_level_str(const char *levelstr)
{
	char *end;
	long level;

	errno = 0;

	/* try to parse integer first */
	level = strtol(levelstr, &end, 10);
	if (end != levelstr && *end == '\0')
		return level;

	if (strcasecmp(levelstr, "ERROR") == 0)
		return CGROUP_LOG_ERROR;
	if (strcasecmp(levelstr, "WARNING") == 0)
		return CGROUP_LOG_WARNING;
	if (strcasecmp(levelstr, "INFO") == 0)
		return CGROUP_LOG_INFO;
	if (strcasecmp(levelstr, "DEBUG") == 0)
		return CGROUP_LOG_DEBUG;

	return CGROUP_DEFAULT_LOGLEVEL;
}

void cgroup_set_loglevel(int loglevel)
{
	if (loglevel != -1)
		cgroup_loglevel = loglevel;
	else {
		char *level_str = getenv("CGROUP_LOGLEVEL");

		if (level_str != NULL)
			cgroup_loglevel = cgroup_parse_log_level_str(level_str);
		else
			cgroup_loglevel = CGROUP_DEFAULT_LOGLEVEL;
	}
}
