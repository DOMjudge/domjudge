// SPDX-License-Identifier: LGPL-2.1-only
/**
 * Copyright RedHat Inc. 2009
 *
 * Authors:	Jan Safranek <jsafrane@redhat.com>
 */

#include "tools-common.h"

#include <libcgroup.h>
#include <libcgroup-internal.h>

#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <getopt.h>
#include <stdio.h>
#include <errno.h>

static const struct option  long_options[] = {
	{"recursive",	      no_argument, NULL, 'r'},
	{"help",	      no_argument, NULL, 'h'},
	{"group",	required_argument, NULL, 'g'},
	{NULL, 0, NULL, 0}
};

struct ext_cgroup_record {
	char name[FILENAME_MAX];	/* controller name */
	char controller[FILENAME_MAX];	/* cgroup name */
	int h_number;			/* hierarchy number */
};


static void usage(int status, const char *program_name)
{
	if (status != 0) {
		err("Wrong input parameters,");
		err(" try %s --help' for more information.\n", program_name);
		return;
	}

	info("Usage: %s [-h] [-r] [[-g] <controllers>:<path>] ...\n", program_name);
	info("Remove control group(s)\n");
	info("  -g <controllers>:<path>	Control group to be removed (-g is optional)\n");
	info("  -h, --help			Display this help\n");
	info("  -r, --recursive		Recursively remove all subgroups\n");
#ifdef WITH_SYSTEMD
	info("  -b				Ignore default systemd delegate hierarchy\n");
#endif
}

/*
 * Skip adding controller which points to the same cgroup when delete
 * cgroup with specifying multi controllers. Just skip controller which
 * cgroup and hierarchy number is same
 */
static int skip_add_controller(int counter, int *skip, struct ext_cgroup_record *ecg_list)
{
	struct controller_data info;
	void *handle;
	int ret = 0;
	int k;

	/* find out hierarchy number of added cgroup */
	ecg_list[counter].h_number = 0;

	ret = cgroup_get_all_controller_begin(&handle, &info);
	while (ret == 0) {
		if (!strcmp(info.name, ecg_list[counter].name)) {
			/* hierarchy number found out, set it */
			ecg_list[counter].h_number = info.hierarchy;
			break;
		}
		ret = cgroup_get_all_controller_next(&handle, &info);
	}
	cgroup_get_all_controller_end(&handle);

	/* deal with cgroup_get_controller_begin/next ret values */
	if (ret == ECGEOF)
		ret = 0;
	if (ret) {
		err("cgroup_get_controller_begin/next failed(%s)\n", cgroup_strerror(ret));
		return ret;
	}

	/* found out whether the hierarchy should be skipped */
	*skip = 0;
	for (k = 0; k < counter; k++) {
		if ((!strcmp(ecg_list[k].name, ecg_list[counter].name)) &&
		    (ecg_list[k].h_number == ecg_list[counter].h_number)) {
			/* we found a control group in the same hierarchy */
			if (strcmp(ecg_list[k].controller, ecg_list[counter].controller)) {
				/*
				 * it is a different controller -> if there
				 * is not one cgroup for the same controller,
				 * skip it
				 */
				*skip = 1;
			} else {
				/*
				 * there is the identical group,controller
				 * pair don't skip it
				 */
				*skip = 0;
				return ret;
			}
		}
	}

	return ret;
}


int main(int argc, char *argv[])
{
	int ignore_default_systemd_delegate_slice = 0;
	struct cgroup_group_spec **cgroup_list = NULL;
	struct ext_cgroup_record *ecg_list = NULL;
	struct cgroup_controller *cgc;
	struct cgroup *cgroup;

	int final_ret = 0;
	int counter = 0;
	int flags = 0;
	int max = 0;
	int ret = 0;
	int i, j, c;
	int skip;

	if (argc < 2) {
		usage(1, argv[0]);
		exit(EXIT_BADARGS);
	}

	/* initialize libcg */
	ret = cgroup_init();
	if (ret) {
		err("%s: libcgroup initialization failed: %s\n", argv[0], cgroup_strerror(ret));
		goto err;
	}

	cgroup_list = calloc(argc, sizeof(struct cgroup_group_spec *));
	if (cgroup_list == NULL) {
		err("%s: out of memory\n", argv[0]);
		ret = -1;
		goto err;
	}

	ecg_list = calloc(argc, sizeof(struct ext_cgroup_record));
	if (ecg_list == NULL) {
		err("%s: out of memory\n", argv[0]);
		ret = -1;
		goto err;
	}

	/* Parse arguments */
#ifdef WITH_SYSTEMD
	while ((c = getopt_long(argc, argv, "rhg:b", long_options, NULL)) > 0) {
		switch (c) {
		case 'b':
			ignore_default_systemd_delegate_slice = 1;
			break;
#else
	while ((c = getopt_long(argc, argv, "rhg:", long_options, NULL)) > 0) {
		switch (c) {
#endif
		case 'r':
			flags |= CGFLAG_DELETE_RECURSIVE;
			break;
		case 'g':
			ret = parse_cgroup_spec(cgroup_list, optarg, argc);
			if (ret != 0) {
				err("%s: error parsing cgroup '%s'", argv[0], optarg);
				ret = EXIT_BADARGS;
				goto err;
			}
			break;
		case 'h':
			usage(0, argv[0]);
			ret = 0;
			goto err;
		default:
			usage(1, argv[0]);
			ret = EXIT_BADARGS;
			goto err;
		}
	}

	/* this is false always for disable-systemd */
	if (!ignore_default_systemd_delegate_slice)
		cgroup_set_default_systemd_cgroup();

	/* parse groups on command line */
	for (i = optind; i < argc; i++) {
		ret = parse_cgroup_spec(cgroup_list, argv[i], argc);
		if (ret != 0) {
			err("%s: error parsing cgroup '%s'\n", argv[0], argv[i]);
			ret = EXIT_BADARGS;
			goto err;
		}
	}

	/* for each cgroup to be deleted */
	for (i = 0; i < argc; i++) {
		if (!cgroup_list[i])
			break;

		/* create the new cgroup structure */
		cgroup = cgroup_new_cgroup(cgroup_list[i]->path);
		if (!cgroup) {
			ret = ECGFAIL;
			err("%s: can't create new cgroup: %s\n", argv[0], cgroup_strerror(ret));
			goto err;
		}

		/* add controllers to the cgroup */
		j = 0;
		while (cgroup_list[i]->controllers[j]) {
			skip = 0;
			/*
			 * save controller name, cg name and hierarchy
			 * number to determine whether we should skip
			 * adding controller
			 */
			if (counter == max) {
				/*
				 * there is not enough space to store
				 * them, create it
				 */
				max = max + argc;
				ecg_list = (struct ext_cgroup_record *)
					realloc(ecg_list, max * sizeof(struct ext_cgroup_record));
				if (!ecg_list) {
					err("%s: not enough memory\n", argv[0]);
					final_ret = -1;
					goto err;
				}
			}

			strncpy(ecg_list[counter].controller,
				cgroup_list[i]->controllers[j], FILENAME_MAX);
			ecg_list[counter].controller[FILENAME_MAX - 1] = '\0';

			strncpy(ecg_list[counter].name,
				cgroup_list[i]->path, FILENAME_MAX);
			ecg_list[counter].name[FILENAME_MAX - 1] = '\0';

			ret = skip_add_controller(counter, &skip, ecg_list);
			if (ret)
				goto err;

			if (skip) {
				/* don't add the controller, goto next one */
				goto next;
			}

			cgc = cgroup_add_controller(cgroup, cgroup_list[i]->controllers[j]);
			if (!cgc) {
				ret = ECGFAIL;
				err("%s: controller %s can't be added\n", argv[0],
				    cgroup_list[i]->controllers[j]);
				cgroup_free(&cgroup);
				goto err;
			}
next:
			counter++;
			j++;
		}

		ret = cgroup_delete_cgroup_ext(cgroup, flags);
		/* Remember the errors and continue, try to remove all groups. */
		if (ret != 0) {
			err("%s: cannot remove group '%s': %s\n", argv[0], cgroup->name,
			    cgroup_strerror(ret));
			final_ret = ret;
		}
		cgroup_free(&cgroup);
	}

	ret = final_ret;

err:
	if (ecg_list)
		free(ecg_list);

	if (cgroup_list) {
		for (i = 0; i < argc; i++) {
			if (cgroup_list[i])
				cgroup_free_group_spec(cgroup_list[i]);
		}
		free(cgroup_list);
	}

	return ret;
}
