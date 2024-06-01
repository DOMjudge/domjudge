// SPDX-License-Identifier: LGPL-2.1-only
/**
 * Libcgroup abstraction layer mappings
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

const struct cgroup_abstraction_map cgroup_v1_to_v2_map[] = {
	/* cpu controller */
	{cgroup_convert_int, "cpu.shares", (void *)1024, "cpu.weight", (void *)100},
	{cgroup_convert_cpu_quota_to_max, "cpu.cfs_quota_us", NULL, "cpu.max", NULL},
	{cgroup_convert_cpu_period_to_max, "cpu.cfs_period_us", NULL, "cpu.max", NULL},
	{cgroup_convert_unmappable, "cpu.stat", NULL, "cpu.stat", NULL},

	/* cpuset controller */
	{cgroup_convert_name_only, "cpuset.effective_cpus", NULL, "cpuset.cpus.effective", NULL},
	{cgroup_convert_name_only, "cpuset.effective_mems", NULL, "cpuset.mems.effective", NULL},
	{cgroup_convert_passthrough, "cpuset.cpus", NULL, "cpuset.cpus", NULL},
	{cgroup_convert_passthrough, "cpuset.mems", NULL, "cpuset.mems", NULL},
	{cgroup_convert_cpuset_to_partition, "cpuset.cpu_exclusive", NULL,
		"cpuset.cpus.partition", NULL},
	{cgroup_convert_unmappable, "cpuset.mem_exclusive", NULL, NULL, NULL},
	{cgroup_convert_unmappable, "cpuset.mem_hardwall", NULL, NULL, NULL},
	{cgroup_convert_unmappable, "cpuset.memory_migrate", NULL, NULL, NULL},
	{cgroup_convert_unmappable, "cpuset.memory_pressure", NULL, NULL, NULL},
	{cgroup_convert_unmappable, "cpuset.mem_pressure_enabled", NULL, NULL, NULL},
	{cgroup_convert_unmappable, "cpuset.memory_spread_page", NULL, NULL, NULL},
	{cgroup_convert_unmappable, "cpuset.memory_spread_slab", NULL, NULL, NULL},
	{cgroup_convert_unmappable, "cpuset.sched_load_balance", NULL, NULL, NULL},
	{cgroup_convert_unmappable, "cpuset.sched_relax_domain_level", NULL, NULL, NULL},
};
const int cgroup_v1_to_v2_map_sz = sizeof(cgroup_v1_to_v2_map) / sizeof(cgroup_v1_to_v2_map[0]);

const struct cgroup_abstraction_map cgroup_v2_to_v1_map[] = {
	/* cpu controller */
	{cgroup_convert_int, "cpu.weight", (void *)100, "cpu.shares", (void *)1024},
	{cgroup_convert_cpu_max_to_quota, "cpu.max", NULL, "cpu.cfs_quota_us", NULL},
	{cgroup_convert_cpu_max_to_period, "cpu.max", NULL, "cpu.cfs_period_us", NULL},
	{cgroup_convert_unmappable, "cpu.stat", NULL, "cpu.stat", NULL},

	/* cpuset controller */
	{cgroup_convert_name_only, "cpuset.cpus.effective", NULL, "cpuset.effective_cpus", NULL},
	{cgroup_convert_name_only, "cpuset.mems.effective", NULL, "cpuset.effective_mems", NULL},
	{cgroup_convert_passthrough, "cpuset.cpus", NULL, "cpuset.cpus", NULL},
	{cgroup_convert_passthrough, "cpuset.mems", NULL, "cpuset.mems", NULL},
	{cgroup_convert_cpuset_to_exclusive, "cpuset.cpus.partition", NULL,
		"cpuset.cpu_exclusive", NULL},
};
const int cgroup_v2_to_v1_map_sz = sizeof(cgroup_v2_to_v1_map) / sizeof(cgroup_v2_to_v1_map[0]);
