/* SPDX-License-Identifier: LGPL-2.1-only */
/**
 * Libcgroup abstraction layer mappings
 *
 * Copyright (c) 2021-2022 Oracle and/or its affiliates.
 * Author: Tom Hromatka <tom.hromatka@oracle.com>
 */

#ifndef __ABSTRACTION_MAP
#define __ABSTRACTION_MAP

#ifdef __cplusplus
extern "C" {
#endif

struct cgroup_abstraction_map {
	/*
	 * if the conversion isn't a one-to-one mapping or the mathematical
	 * conversion is unique, create a custom conversion function.
	 */
	int (*cgroup_convert)(struct cgroup_controller * const dst_cgc, const char * const in_value,
			      const char * const out_setting, void *in_dflt, void *out_dflt);
	char *in_setting;
	void *in_dflt;
	char *out_setting;
	void *out_dflt;
};

extern const struct cgroup_abstraction_map cgroup_v1_to_v2_map[];
extern const int cgroup_v1_to_v2_map_sz;

extern const struct cgroup_abstraction_map cgroup_v2_to_v1_map[];
extern const int cgroup_v2_to_v1_map_sz;

#ifdef __cplusplus
} /* extern "C" */
#endif

#endif /* __ABSTRACTION_MAP */
