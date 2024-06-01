// SPDX-License-Identifier: LGPL-2.1-only
#include <libcgroup.h>

#include <stdlib.h>
#include <stdio.h>

/*
 * Assumes the cgroup is already mounted at /cgroup/memory/a
 * Assumes some processes are already in the cgroup
 * Assumes it is the memory controller is mounted in at that point
 */
int main(void)
{
	pid_t *pids;
	int size;
	int ret;
	int i;

	ret = cgroup_init();
	if (ret) {
		printf("FAIL: cgroup_init failed with %s\n",
		       cgroup_strerror(ret));
		exit(3);
	}

	ret = cgroup_get_procs("a", "memory", &pids, &size);
	if (ret) {
		printf("FAIL: cgroup_get_procs failed with %s\n",
		       cgroup_strerror(ret));
		exit(3);
	}

	for (i = 0; i < size; i++)
		printf("%u\n", pids[i]);

	return 0;
}
