// SPDX-License-Identifier: LGPL-2.1-only
#include <libcgroup.h>

#include <stdlib.h>
#include <unistd.h>
#include <string.h>
#include <stdio.h>

#include <sys/types.h>

int read_stats(char *path, char *controller)
{
	struct cgroup_stat stat;
	void *handle;
	int ret;

	ret = cgroup_read_stats_begin(controller, path,  &handle, &stat);
	if (ret != 0) {
		fprintf(stderr, "stats read failed\n");
		return -1;
	}

	printf("Stats for %s:\n", path);
	printf("%s: %s", stat.name, stat.value);

	while ((ret = cgroup_read_stats_next(&handle, &stat)) !=
			ECGEOF) {
		printf("%s: %s", stat.name, stat.value);
	}

	cgroup_read_stats_end(&handle);
	printf("\n");
	return 0;
}

int main(int argc, char *argv[])
{
	struct cgroup_file_info info;
	char cgroup_path[FILENAME_MAX];
	char *controller;
	int root_len;
	void *handle;
	int lvl;
	int ret;

	if (argc < 2) {
		fprintf(stderr, "Usage %s: <controller name>\n",
			argv[0]);
		exit(EXIT_FAILURE);
	}

	controller = argv[1];

	ret = cgroup_init();
	if (ret != 0) {
		fprintf(stderr, "init failed\n");
		exit(EXIT_FAILURE);
	}

	ret = cgroup_walk_tree_begin(controller, "/", 0, &handle, &info, &lvl);

	if (ret != 0) {
		fprintf(stderr, "Walk failed\n");
		exit(EXIT_FAILURE);
	}

	root_len = strlen(info.full_path) - 1;
	strncpy(cgroup_path, info.path, FILENAME_MAX - 1);
	ret = read_stats(cgroup_path, controller);
	if (ret < 0)
		exit(EXIT_FAILURE);

	while ((ret = cgroup_walk_tree_next(0, &handle, &info, lvl)) !=
			ECGEOF) {
		if (info.type != CGROUP_FILE_TYPE_DIR)
			continue;
		strncpy(cgroup_path, info.full_path + root_len, FILENAME_MAX - 1);
		strcat(cgroup_path, "/");
		ret = read_stats(cgroup_path, controller);
		if (ret < 0)
			exit(EXIT_FAILURE);
	}
	cgroup_walk_tree_end(&handle);

	return EXIT_SUCCESS;
}
