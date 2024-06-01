/* SPDX-License-Identifier: LGPL-2.1-only */
/**
 * Libcgroup abstraction layer prototypes and structs
 *
 * Copyright (c) 2021-2022 Oracle and/or its affiliates.
 * Author: Tom Hromatka <tom.hromatka@oracle.com>
 */

#ifndef __ABSTRACTION_COMMON
#define __ABSTRACTION_COMMON

#ifdef __cplusplus
extern "C" {
#endif

#include "config.h"

#include <libcgroup.h>
#include "libcgroup-internal.h"

/**
 * Convert a string to a long
 *
 * @param in_str String to be converted
 * @param base Integer base
 * @param out_value Pointer to hold the output long value
 *
 * @return 0 on success,
 *	   ECGFAIL if the conversion to long failed,
 *	   ECGINVAL upon an invalid parameter
 */
int cgroup_strtol(const char * const in_str, int base, long * const out_value);

/**
 * Convert an integer setting to another integer setting
 *
 * @param dst_cgc Destination cgroup controller
 * @param in_value Contents of the input setting
 * @param out_setting Destination cgroup setting
 * @param in_dflt Default value of the input setting (used to scale the value)
 * @param out_dflt Default value of the output setting (used to scale the value)
 */
int cgroup_convert_int(struct cgroup_controller * const dst_cgc, const char * const in_value,
		       const char * const out_setting, void *in_dflt, void *out_dflt);

/**
 * Convert only the name from one setting to another.  The contents remain
 * the same
 *
 * @param dst_cgc Destination cgroup controller
 * @param in_value Contents of the input setting
 * @param out_setting Destination cgroup setting
 * @param in_dflt Default value of the input setting (unused)
 * @param out_dflt Default value of the output setting (unused)
 */
int cgroup_convert_name_only(struct cgroup_controller * const dst_cgc, const char * const in_value,
			     const char * const out_setting, void *in_dflt, void *out_dflt);

/**
 * No conversion necessary.  The name and the contents are the same
 *
 * @param dst_cgc Destination cgroup controller
 * @param in_value Contents of the input setting
 * @param out_setting Destination cgroup setting
 * @param in_dflt Default value of the input setting (unused)
 * @param out_dflt Default value of the output setting (unused)
 */
int cgroup_convert_passthrough(struct cgroup_controller * const dst_cgc,
			       const char * const in_value, const char * const out_setting,
			       void *in_dflt, void *out_dflt);

/**
 * Convert from an unmapple setting
 *
 * @param dst_cgc Destination cgroup controller (unused)
 * @param in_value Contents of the input setting (unsed)
 * @param out_setting Destination cgroup setting (unused)
 * @param in_dflt Default value of the input setting (unused)
 * @param out_dflt Default value of the output setting (unused)
 * @return Always returns ECGNOVERSIONCONVERT
 */
int cgroup_convert_unmappable(struct cgroup_controller * const dst_cgc,
			      const char * const in_value, const char * const out_setting,
			      void *in_dflt, void *out_dflt);

/* cpu */
int cgroup_convert_cpu_nto1(struct cgroup_controller * const out_cgc,
			    struct cgroup_controller * const in_cgc);

int cgroup_convert_cpu_quota_to_max(struct cgroup_controller * const dst_cgc,
				    const char * const in_value, const char * const out_setting,
				    void *in_dflt, void *out_dflt);

int cgroup_convert_cpu_period_to_max(struct cgroup_controller * const dst_cgc,
				     const char * const in_value, const char * const out_setting,
				     void *in_dflt, void *out_dflt);

int cgroup_convert_cpu_max_to_quota(struct cgroup_controller * const dst_cgc,
				    const char * const in_value, const char * const out_setting,
				    void *in_dflt, void *out_dflt);

int cgroup_convert_cpu_max_to_period(struct cgroup_controller * const dst_cgc,
				     const char * const in_value, const char * const out_setting,
				     void *in_dflt, void *out_dflt);

/* cpuset */
int cgroup_convert_cpuset_to_exclusive(struct cgroup_controller * const dst_cgc,
				       const char * const in_value, const char * const out_setting,
				       void *in_dflt, void *out_dflt);

int cgroup_convert_cpuset_to_partition(struct cgroup_controller * const dst_cgc,
				       const char * const in_value, const char * const out_setting,
				       void *in_dflt, void *out_dflt);

#ifdef __cplusplus
} /* extern "C" */
#endif

#endif /* __ABSTRACTION_COMMON */
