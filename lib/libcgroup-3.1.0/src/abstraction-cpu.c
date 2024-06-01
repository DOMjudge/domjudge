// SPDX-License-Identifier: LGPL-2.1-only
/**
 * Libcgroup abstraction layer for the cpu controller
 *
 * Copyright (c) 2021-2022 Oracle and/or its affiliates.
 * Author: Tom Hromatka <tom.hromatka@oracle.com>
 */

#include "abstraction-common.h"
#include "abstraction-map.h"

#include <libcgroup.h>
#include <libcgroup-internal.h>

#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <errno.h>
#include <stdio.h>

#define LL_MAX 8192

static const char * const MAX = "max";
static const char * const CPU_MAX = "cpu.max";
static const char * const CFS_QUOTA_US = "cpu.cfs_quota_us";
static const char * const CFS_PERIOD_US = "cpu.cfs_period_us";

static int read_setting(const char * const cgroup_name, const char * const controller_name,
			const char * const setting_name, char ** const value)
{
	char tmp_line[LL_MAX];
	void *handle;
	int ret;

	ret = cgroup_read_value_begin(controller_name, cgroup_name, setting_name, &handle,
				      tmp_line, LL_MAX);
	if (ret == ECGEOF)
		goto read_end;
	else if (ret != 0)
		goto end;

	*value = strdup(tmp_line);
	if ((*value) == NULL)
		ret = ECGOTHER;

read_end:
	cgroup_read_value_end(&handle);
	if (ret == ECGEOF)
		ret = 0;
end:
	return ret;
}

static int get_max(struct cgroup_controller * const cgc, char ** const max)
{
	return read_setting(cgc->cgroup->name, "cpu", "cpu.max", max);
}

static int get_quota_from_max(struct cgroup_controller * const cgc, char ** const quota)
{
	char *token, *max = NULL, *saveptr = NULL;
	int ret;

	ret = get_max(cgc, &max);
	if (ret)
		goto out;

	token = strtok_r(max, " ", &saveptr);

	*quota = strdup(token);
	if ((*quota) == NULL)
		ret = ECGOTHER;

out:
	if (max)
		free(max);

	return ret;
}

static int get_period_from_max(struct cgroup_controller * const cgc, char ** const period)
{
	char *token, *max = NULL, *saveptr = NULL;
	int ret;

	ret = get_max(cgc, &max);
	if (ret)
		goto out;

	token = strtok_r(max, " ", &saveptr);
	token = strtok_r(NULL, " ", &saveptr);

	*period = strdup(token);
	if ((*period) == NULL)
		ret = ECGOTHER;

out:
	if (max)
		free(max);

	return ret;
}

int cgroup_convert_cpu_quota_to_max(struct cgroup_controller * const dst_cgc,
				    const char * const in_value, const char * const out_setting,
				    void *in_dflt, void *out_dflt)
{
	char max_line[LL_MAX] = {0};
	char *period = NULL;
	int ret;

	if (strlen(in_value) == 0) {
		/* There's no value to convert.  Populate the setting */
		ret = cgroup_add_value_string(dst_cgc, out_setting, NULL);
		if (ret)
			goto out;
	} else {
		ret = get_period_from_max(dst_cgc, &period);
		if (ret)
			goto out;

		if (strcmp(in_value, "-1") == 0)
			snprintf(max_line, LL_MAX, "%s %s", MAX, period);
		else
			snprintf(max_line, LL_MAX, "%s %s", in_value, period);

		ret = cgroup_add_value_string(dst_cgc, out_setting, max_line);
		if (ret)
			goto out;
	}

	dst_cgc->values[dst_cgc->index - 1]->prev_name = strdup(CFS_QUOTA_US);

out:
	if (period)
		free(period);

	return ret;
}

int cgroup_convert_cpu_period_to_max(struct cgroup_controller * const dst_cgc,
				     const char * const in_value, const char * const out_setting,
				     void *in_dflt, void *out_dflt)
{
	char max_line[LL_MAX] = {0};
	char *quota = NULL;
	int ret;

	if (strlen(in_value) == 0) {
		/* There's no value to convert.  Populate the setting and return */
		ret = cgroup_add_value_string(dst_cgc, out_setting, NULL);
		if (ret)
			goto out;
	} else {
		ret = get_quota_from_max(dst_cgc, &quota);
		if (ret)
			goto out;

		if (strcmp(in_value, "-1") == 0)
			snprintf(max_line, LL_MAX, "%s %s", quota, MAX);
		else
			snprintf(max_line, LL_MAX, "%s %s", quota, in_value);
		ret = cgroup_add_value_string(dst_cgc, out_setting, max_line);
		if (ret)
			goto out;
	}

	dst_cgc->values[dst_cgc->index - 1]->prev_name = strdup(CFS_PERIOD_US);

out:
	if (quota)
		free(quota);

	return ret;
}

int cgroup_convert_cpu_max_to_quota(struct cgroup_controller * const dst_cgc,
				    const char * const in_value, const char * const out_setting,
				    void *in_dflt, void *out_dflt)
{
	char *token, *copy = NULL, *saveptr = NULL;
	int ret;

	if (strlen(in_value) == 0) {
		/* There's no value to convert.  Populate the setting and return */
		return cgroup_add_value_string(dst_cgc, out_setting, NULL);
	}

	copy = strdup(in_value);
	if (!copy)
		return ECGOTHER;

	token = strtok_r(copy, " ", &saveptr);

	if (strcmp(token, MAX) == 0)
		ret = cgroup_add_value_string(dst_cgc, out_setting, "-1");
	else
		ret = cgroup_add_value_string(dst_cgc, out_setting, token);

	if (copy)
		free(copy);

	return ret;
}

int cgroup_convert_cpu_max_to_period(struct cgroup_controller * const dst_cgc,
				     const char * const in_value, const char * const out_setting,
				     void *in_dflt, void *out_dflt)
{
	char *token, *copy = NULL, *saveptr = NULL;
	int ret;

	if (strlen(in_value) == 0) {
		/* There's no value to convert.  Populate the setting and return */
		return cgroup_add_value_string(dst_cgc, out_setting, NULL);
	}

	copy = strdup(in_value);
	if (!copy)
		return ECGOTHER;

	token = strtok_r(copy, " ", &saveptr);
	token = strtok_r(NULL, " ", &saveptr);

	ret = cgroup_add_value_string(dst_cgc, out_setting, token);

	if (copy)
		free(copy);

	return ret;
}

int cgroup_convert_cpu_nto1(struct cgroup_controller * const out_cgc,
			    struct cgroup_controller * const in_cgc)
{
	char *cfs_quota = NULL, *cfs_period = NULL;
	char max_line[LL_MAX] = {0};
	int i, ret = 0;

	for (i = 0; i < in_cgc->index; i++) {
		if (strcmp(in_cgc->values[i]->name, CFS_QUOTA_US) == 0)
			cfs_quota = in_cgc->values[i]->value;
		else if (strcmp(in_cgc->values[i]->name, CFS_PERIOD_US) == 0)
			cfs_period = in_cgc->values[i]->value;
	}

	if (cfs_quota && cfs_period) {
		if (strcmp(cfs_quota, "-1") == 0) {
			snprintf(max_line, LL_MAX, "%s %s", MAX, cfs_period);
			max_line[LL_MAX - 1] = '\0';
		} else {
			snprintf(max_line, LL_MAX, "%s %s", cfs_quota, cfs_period);
			max_line[LL_MAX - 1] = '\0';
		}

		ret = cgroup_add_value_string(out_cgc, CPU_MAX, max_line);
		if (ret)
			goto out;

		ret = cgroup_remove_value(in_cgc, CFS_QUOTA_US);
		if (ret)
			goto out;

		ret = cgroup_remove_value(in_cgc, CFS_PERIOD_US);
		if (ret)
			goto out;
	}

out:
	return ret;
}
