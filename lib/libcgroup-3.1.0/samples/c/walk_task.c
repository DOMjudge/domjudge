// SPDX-License-Identifier: LGPL-2.1-only
#include <libcgroup.h>

#include <stdlib.h>
#include <string.h>
#include <errno.h>
#include <stdio.h>

int main(int argc, char *argv[])
{
	char *group = NULL;
	void *handle;
	int ret, i;

	if (argc < 2) {
		printf("No list of groups provided\n");
		return -1;
	}

	ret = cgroup_init();
	if (ret) {
		printf("cgroup_init failed with %s\n", cgroup_strerror(ret));
		return -1;
	}

	for (i = 1; i < argc; i++) {
		pid_t pid;

		group = strdup(argv[i]);
		printf("Printing the details of groups %s\n", group);

		ret = cgroup_get_task_begin(group, "cpu", &handle, &pid);
		while (!ret) {
			printf("Pid is %u\n", pid);
			ret = cgroup_get_task_next(&handle, &pid);
			if (ret && ret != ECGEOF) {
				printf("cgroup_get_task_next failed with %s\n",
				       cgroup_strerror(ret));
				if (ret == ECGOTHER)
					printf("failure with %s\n",
					       strerror(errno));
				return -1;
			}
		}
		free(group);
		group = NULL;
		ret = cgroup_get_task_end(&handle);
	}

	return 0;
}
