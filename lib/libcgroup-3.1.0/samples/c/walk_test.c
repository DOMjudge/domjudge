// SPDX-License-Identifier: LGPL-2.1-only
#include <libcgroup.h>

#include <stdlib.h>
#include <unistd.h>
#include <string.h>
#include <stdio.h>

#include <sys/types.h>

void visit_node(struct cgroup_file_info *info, char *root)
{
	if (info->type == CGROUP_FILE_TYPE_DIR) {
		printf("path %s, parent %s, relative %s, full %s\n",
			info->path, info->parent,
			info->full_path + strlen(root) - 1,
			info->full_path);
	}
}

int main(int argc, char *argv[])
{
	struct cgroup_file_info info;
	char root[FILENAME_MAX];
	char *controller;
	void *handle;
	int lvl, i;
	int ret;

	if (argc < 2) {
		fprintf(stderr, "Usage %s: <controller name>\n",
			argv[0]);
		exit(EXIT_FAILURE);
	}

	controller = argv[1];

	ret = cgroup_init();
	if (ret != 0) {
		fprintf(stderr, "Init failed\n");
		exit(EXIT_FAILURE);
	}

	ret = cgroup_walk_tree_begin(controller, "/", 0, &handle, &info, &lvl);

	if (ret != 0) {
		fprintf(stderr, "Walk failed\n");
		exit(EXIT_FAILURE);
	}

	strcpy(root, info.full_path);
	printf("Begin pre-order walk\n");
	printf("root is %s\n", root);
	visit_node(&info, root);
	while ((ret = cgroup_walk_tree_next(0, &handle, &info, lvl)) !=
			ECGEOF) {
		visit_node(&info, root);
	}
	cgroup_walk_tree_end(&handle);

	printf("pre-order walk finished\n");
	ret = cgroup_walk_tree_begin(controller, "/", 0, &handle, &info, &lvl);

	if (ret != 0) {
		fprintf(stderr, "Walk failed\n");
		exit(EXIT_FAILURE);
	}

	ret = cgroup_walk_tree_set_flags(&handle, CGROUP_WALK_TYPE_POST_DIR);

	if (ret) {
		fprintf(stderr, "Walk failed with %s\n", cgroup_strerror(ret));
		exit(EXIT_FAILURE);
	}

	strcpy(root, info.full_path);
	printf("Begin post-order walk\n");
	printf("root is %s\n", root);
	visit_node(&info, root);
	while ((ret = cgroup_walk_tree_next(0, &handle, &info, lvl)) !=
			ECGEOF) {
		visit_node(&info, root);
	}
	cgroup_walk_tree_end(&handle);
	printf("post order walk finished\n");

	ret = cgroup_walk_tree_begin(controller, "/a", 2, &handle, &info, &lvl);

	if (ret != 0) {
		fprintf(stderr, "Walk failed\n");
		exit(EXIT_FAILURE);
	}
	strcpy(root, info.full_path);
	printf("root is %s\n", root);
	visit_node(&info, root);
	while ((ret = cgroup_walk_tree_next(2, &handle, &info, lvl)) !=
			ECGEOF) {
		visit_node(&info, root);
	}
	cgroup_walk_tree_end(&handle);

	/*
	 * Walk only the first five nodes
	 */
	i = 0;
	printf("Walking the first 5 nodes\n");
	ret = cgroup_walk_tree_begin(controller, "/", 0, &handle, &info, &lvl);

	if (ret != 0) {
		fprintf(stderr, "Walk failed\n");
		exit(EXIT_FAILURE);
	}
	strcpy(root, info.full_path);
	printf("root is %s\n", root);
	visit_node(&info, root);
	i++;
	while ((ret = cgroup_walk_tree_next(0, &handle, &info, lvl)) !=
			ECGEOF) {
		visit_node(&info, root);
		if (++i >= 5)
			break;
	}
	cgroup_walk_tree_end(&handle);

	return EXIT_SUCCESS;
}
