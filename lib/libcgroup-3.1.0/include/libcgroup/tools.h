/* SPDX-License-Identifier: LGPL-2.1-only */
/**
 * Libcgroup tools header file
 *
 * Copyright (c) 2021-2022 Oracle and/or its affiliates.
 * Author: Tom Hromatka <tom.hromatka@oracle.com>
 */

#ifndef _LIBCGROUP_TOOLS_H
#define _LIBCGROUP_TOOLS_H

#ifndef _LIBCGROUP_H_INSIDE
#error "Only <libcgroup.h> should be included directly."
#endif

#ifndef SWIG
#include <features.h>
#include <sys/types.h>
#include <stdbool.h>
#endif

#ifdef __cplusplus
extern "C" {
#endif

/**
 * Read the setting-value pairs in the cgroup sysfs for param cg.
 * cgroup_cgxget() will perform the necessary conversions to match
 * the "on-disk" format and then convert the data back to the
 * requested version.  If successful, cg will be populated with
 * the setting-value pairs.
 *
 * @param cg Input/Output cgroup. Must be initialized and freed by the caller
 * @param version Cgroup version of cg  If set to CGROUP_UNK, the versions
 *		  stored within each controller will be used.  Otherwise this
 *		  value will be used to override the cg param's controller
 *		  versions
 * @param ignore_unmappable Ignore failures due to settings that cannot be
 *			    converted from one cgroup version to another
 */
int cgroup_cgxget(struct cgroup **cg,
		  enum cg_version_t version, bool ignore_unmappable);

/**
 * Write the setting-value pairs in *cg to the cgroup sysfs.
 * cgroup_cgxset() will perform the necessary conversions to match the
 * "on-disk" format prior to writing to the cgroup sysfs.
 *
 * @param cg cgroup instance that will be written to the cgroup sysfs
 * @param version Cgroup version of *cg
 * @param ignore_unmappable Ignore failures due to settings that cannot be
 *                          converted from one cgroup version to another
 */
int cgroup_cgxset(const struct cgroup * const cg,
		  enum cg_version_t version, bool ignore_unmappable);

#ifdef __cplusplus
} /* extern "C" */
#endif

#endif /* _LIBCGROUP_TOOLS_H */
