// SPDX-License-Identifier: LGPL-2.1-only
#include "../src/libcgroup-internal.h"
#include <libcgroup.h>

#include <stdlib.h>
#include <string.h>
#include <stdio.h>

int main(int argc, char *argv[])
{
	struct cgroup_controller *group_controller = NULL;
	struct cgroup *group = NULL;
	char group_name[] = "/";
	char *name;
	int count;
	int ret;
	int i, j;

	if (argc < 2) {
		printf("no list of groups provided\n");
		return -1;
	}

	ret = cgroup_init();
	if (ret) {
		printf("cgroup_init failed with %s\n", cgroup_strerror(ret));
		exit(1);
	}

	group = cgroup_new_cgroup(group_name);
	if (group == NULL) {
		printf("cannot create group '%s'\n", group_name);
		return -1;
	}

	ret = cgroup_get_cgroup(group);
	if (ret != 0) {
		printf("cannot read group '%s': %s\n",
			group_name, cgroup_strerror(ret));
	}

	for (i = 1; i < argc; i++) {

		group_controller = cgroup_get_controller(group, argv[i]);
		if (group_controller == NULL) {
			printf("cannot find controller '%s' in group '%s'\n",
			       argv[i], group_name);
			ret = -1;
			continue;
		}

		count = cgroup_get_value_name_count(group_controller);
		for (j = 0; j < count; j++) {
			name = cgroup_get_value_name(group_controller, j);
			if (name != NULL)
				printf("%s\n", name);
		}
	}

	return ret;
}
