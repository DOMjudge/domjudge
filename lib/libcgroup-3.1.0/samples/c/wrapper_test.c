// SPDX-License-Identifier: LGPL-2.1-only
#include "../src/libcgroup-internal.h"
#include <libcgroup.h>

#include <string.h>
#include <stdio.h>

int main(void)
{
	struct cgroup *cgroup;
	struct cgroup_controller *cgc;
	int fail = 0;

	cgroup = cgroup_new_cgroup("test");
	cgc = cgroup_add_controller(cgroup, "cpu");

	cgroup_add_value_int64(cgc, "cpu.shares", 2048);
	cgroup_add_value_uint64(cgc, "cpu.something", 1000);
	cgroup_add_value_bool(cgc, "cpu.bool", 1);

	if (!strcmp(cgroup->controller[0]->values[0]->name, "cpu.shares")) {
		if (strcmp(cgroup->controller[0]->values[0]->value, "2048")) {
			printf("FAIL for add_value_int\n");
			fail = 1;
		}
	}

	if (!strcmp(cgroup->controller[0]->values[1]->name, "cpu.something")) {
		if (strcmp(cgroup->controller[0]->values[1]->value, "1000")) {
			printf("FAIL for add_value_uint\n");
			fail = 1;
		}
	}

	if (!strcmp(cgroup->controller[0]->values[2]->name, "cpu.bool")) {
		if (strcmp(cgroup->controller[0]->values[2]->value, "1")) {
			printf("FAIL for add_value_bool\n");
			fail = 1;
		}
	}

	if (!fail)
		printf("PASS!\n");

	return fail;
}
