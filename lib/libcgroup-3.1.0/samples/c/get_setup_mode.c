// SPDX-License-Identifier: LGPL-2.1-only
/*
 * Copyright (c) 2023 Oracle and/or its affiliates
 *
 * Author: Kamalesh Babulal <kamalesh.babulal@oracle.com>
 *
 * Description: This file contains the sample code to demonstrate usage of
 * 		cgroup_setup_mode() API.
 */

#include <stdio.h>
#include <stdlib.h>

#include <libcgroup.h>

int main(void)
{
	enum cg_setup_mode_t setup_mode;
	int ret;

	ret = cgroup_init();
	if (ret) {
		printf("cgroup_init failed with %s\n", cgroup_strerror(ret));
		exit(1);
	}

	setup_mode = cgroup_setup_mode();
	switch(setup_mode) {
	case CGROUP_MODE_LEGACY:
		printf("cgroup mode: Legacy\n");
		break;
	case CGROUP_MODE_HYBRID:
		printf("cgroup mode: Hybrid\n");
		break;
	case CGROUP_MODE_UNIFIED:
		printf("cgroup mode: Unified\n");
		break;
	default:
		printf("cgroup mode: Unknown\n");
		break;
	}

	return 0;
}
