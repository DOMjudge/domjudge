// SPDX-License-Identifier: LGPL-2.1-only
/**
 * Libcgroup abstraction layer for the cpuset controller
 *
 * Copyright (c) 2021-2022 Oracle and/or its affiliates.
 * Author: Tom Hromatka <tom.hromatka@oracle.com>
 */

#include "abstraction-common.h"

#include <libcgroup.h>
#include <libcgroup-internal.h>

#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <errno.h>
#include <stdio.h>

static const char * const MEMBER = "member";
static const char * const ROOT = "root";

int cgroup_convert_cpuset_to_exclusive(struct cgroup_controller * const dst_cgc,
				       const char * const in_value, const char * const out_setting,
				       void *in_dflt, void *out_dflt)
{
	int ret;

	if (strcmp(in_value, ROOT) == 0)
		ret = cgroup_add_value_string(dst_cgc, out_setting, "1");
	else
		ret = cgroup_add_value_string(dst_cgc, out_setting, "0");

	return ret;
}

int cgroup_convert_cpuset_to_partition(struct cgroup_controller * const dst_cgc,
				       const char * const in_value,
				       const char * const out_setting,
				       void *in_dflt, void *out_dflt)
{
	int ret;

	if (strcmp(in_value, "1") == 0)
		ret = cgroup_add_value_string(dst_cgc, out_setting, ROOT);
	else
		ret = cgroup_add_value_string(dst_cgc, out_setting, MEMBER);

	return ret;
}
