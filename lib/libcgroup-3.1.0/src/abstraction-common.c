// SPDX-License-Identifier: LGPL-2.1-only
/**
 * Libcgroup abstraction layer
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


int cgroup_strtol(const char * const in_str, int base, long * const out_value)
{
	char *endptr = NULL;
	int ret = 0;

	if (out_value == NULL) {
		cgroup_err("Invalid parameter to %s\n", __func__);
		ret = ECGINVAL;
		goto out;
	}

	errno = 0;
	*out_value = strtol(in_str, &endptr, base);

	/* taken directly from strtol's man page */
	if ((errno == ERANGE && (*out_value == LONG_MAX || *out_value == LONG_MIN)) ||
	    (errno != 0 && *out_value == 0)) {
		cgroup_err("Failed to convert %s from strtol: %s\n", in_str);
		ret = ECGFAIL;
		goto out;
	}

	if (endptr == in_str) {
		cgroup_err("No long value found in %s\n", in_str);
		ret = ECGFAIL;
		goto out;
	}

out:
	return ret;
}

int cgroup_convert_int(struct cgroup_controller * const dst_cgc, const char * const in_value,
		       const char * const out_setting, void *in_dflt, void *out_dflt)
{
#define OUT_VALUE_STR_LEN 20

	long out_dflt_int = (long)out_dflt;
	long in_dflt_int = (long)in_dflt;
	char *out_value_str = NULL;
	long out_value;
	int ret;

	if (!in_value)
		return ECGINVAL;

	if (strlen(in_value) > 0) {
		ret = cgroup_strtol(in_value, 10, &out_value);
		if (ret)
			goto out;

		/* now scale from the input range to the output range */
		out_value = out_value * out_dflt_int / in_dflt_int;

		out_value_str = calloc(sizeof(char), OUT_VALUE_STR_LEN);
		if (!out_value_str) {
			ret = ECGOTHER;
			goto out;
		}

		ret = snprintf(out_value_str, OUT_VALUE_STR_LEN, "%ld", out_value);
		if (ret == OUT_VALUE_STR_LEN) {
			/* we ran out of room in the string. throw an error */
			cgroup_err("output value too large for string: %d\n", out_value);
			ret = ECGFAIL;
			goto out;
		}
	}

	ret = cgroup_add_value_string(dst_cgc, out_setting, out_value_str);

out:
	if (out_value_str)
		free(out_value_str);

	return ret;
}

int cgroup_convert_name_only(struct cgroup_controller * const dst_cgc, const char * const in_value,
			     const char * const out_setting, void *in_dflt, void *out_dflt)
{
	return cgroup_add_value_string(dst_cgc, out_setting, in_value);
}

int cgroup_convert_passthrough(struct cgroup_controller * const dst_cgc,
			       const char * const in_value, const char * const out_setting,
			       void *in_dflt, void *out_dflt)
{
	return cgroup_add_value_string(dst_cgc, out_setting, in_value);
}

int cgroup_convert_unmappable(struct cgroup_controller * const dst_cgc,
			      const char * const in_value, const char * const out_setting,
			      void *in_dflt, void *out_dflt)
{
	return ECGNOVERSIONCONVERT;
}

static int convert_setting(struct cgroup_controller * const out_cgc,
			   const struct control_value * const in_ctrl_val)
{
	const struct cgroup_abstraction_map *convert_tbl;
	int ret = ECGINVAL;
	int tbl_sz = 0;
	int i;

	switch (out_cgc->version) {
	case CGROUP_V1:
		convert_tbl = cgroup_v2_to_v1_map;
		tbl_sz = cgroup_v2_to_v1_map_sz;
		break;
	case CGROUP_V2:
		convert_tbl = cgroup_v1_to_v2_map;
		tbl_sz = cgroup_v1_to_v2_map_sz;
		break;
	default:
		ret = ECGFAIL;
		goto out;
	}

	for (i = 0; i < tbl_sz; i++) {
		/*
		 * For a few settings, e.g.
		 * cpu.max <-> cpu.cfs_quota_us/cpu.cfs_period_us, the
		 * conversion from the N->1 field (cpu.max) back to one of the
		 * other settings cannot be done without prior knowledge of our
		 * desired setting (quota or period in this example).
		 * If prev_name is set, it can guide us back to the correct
		 * mapping.
		 */
		if (strcmp(convert_tbl[i].in_setting, in_ctrl_val->name) == 0 &&
		    (in_ctrl_val->prev_name == NULL ||
		     strcmp(in_ctrl_val->prev_name, convert_tbl[i].out_setting) == 0)) {

			ret = convert_tbl[i].cgroup_convert(out_cgc, in_ctrl_val->value,
							    convert_tbl[i].out_setting,
							    convert_tbl[i].in_dflt,
							    convert_tbl[i].out_dflt);
			if (ret)
				goto out;
		}
	}

out:
	return ret;
}

static int convert_controller(struct cgroup_controller ** const out_cgc,
			      struct cgroup_controller * const in_cgc)
{
	bool unmappable = false;
	int ret;
	int i;


	if (in_cgc->version == (*out_cgc)->version) {
		ret = cgroup_copy_controller_values(*out_cgc, in_cgc);
		/* regardless of success/failure, there's nothing more to do */
		goto out;
	}

	if (strcmp(in_cgc->name, "cpu") == 0) {
		ret = cgroup_convert_cpu_nto1(*out_cgc, in_cgc);
		if (ret)
			goto out;
	}

	for (i = 0; i < in_cgc->index; i++) {
		ret = convert_setting(*out_cgc, in_cgc->values[i]);
		if (ret == ECGNOVERSIONCONVERT) {
			/*
			 * Ignore unmappable errors while they happen, as
			 * there may be mappable settings after that
			 */
			unmappable = true;
			ret = 0;
		} else if (ret) {
			/* immediately fail on all other errors */
			goto out;
		}
	}

out:
	if (ret == 0 && unmappable) {
		/* The only error received was an unmappable error. Return it. */
		ret = ECGNOVERSIONCONVERT;

		if ((*out_cgc)->index == 0) {
			/*
			 * No settings were successfully converted. Remove this
			 * controller so that tools like cgxget aren't confused
			 */
			cgroup_free_controller(*out_cgc);
			*out_cgc = NULL;
		}
	}

	return ret;
}

int cgroup_convert_cgroup(struct cgroup * const out_cgroup, enum cg_version_t out_version,
			  const struct cgroup * const in_cgroup, enum cg_version_t in_version)
{
	struct cgroup_controller *cgc;
	bool unmappable = false;
	int ret = 0;
	int i;

	for (i = 0; i < in_cgroup->index; i++) {
		cgc = cgroup_add_controller(out_cgroup, in_cgroup->controller[i]->name);
		if (cgc == NULL) {
			ret = ECGFAIL;
			goto out;
		}

		/* the user has overridden the version */
		if (in_version == CGROUP_V1 || in_version == CGROUP_V2)
			in_cgroup->controller[i]->version = in_version;

		if (strcmp(CGROUP_FILE_PREFIX, cgc->name) == 0)
			/*
			 * libcgroup only supports accessing cgroup.* files
			 * on cgroup v2 filesystems.
			 */
			cgc->version = CGROUP_V2;
		else
			cgc->version = out_version;

		if (cgc->version == CGROUP_UNK || cgc->version == CGROUP_DISK) {
			ret = cgroup_get_controller_version(cgc->name, &cgc->version);
			if (ret)
				goto out;
		}

		ret = convert_controller(&cgc, in_cgroup->controller[i]);
		if (ret == ECGNOVERSIONCONVERT) {
			/*
			 * Ignore unmappable errors while they happen, as
			 * there may be mappable settings after that
			 */
			unmappable = true;

			if (!cgc)
				/*
				 * The converted controller had no settings
				 * and was removed.  It's up to us to manage
				 * the controller count
				 */
				out_cgroup->index--;
		} else if (ret) {
			/* immediately fail on all other errors */
			goto out;
		}
	}

out:
	if (ret == 0 && unmappable)
		/*
		 * The only error received was an unmappable
		 * error. Return it.
		 */
		ret = ECGNOVERSIONCONVERT;

	return ret;
}
