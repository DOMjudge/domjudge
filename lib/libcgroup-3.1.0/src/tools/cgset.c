// SPDX-License-Identifier: LGPL-2.1-only
#include "tools-common.h"

#include <libcgroup.h>
#include <libcgroup-internal.h>

#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <getopt.h>
#include <stdio.h>

#define FL_RULES	1
#define FL_COPY		2

enum {
	COPY_FROM_OPTION = CHAR_MAX + 1
};

#ifndef UNIT_TEST
static const struct option long_options[] = {
	{"rule",	required_argument, NULL, 'r'},
	{"help",	      no_argument, NULL, 'h'},
	{"copy-from",	required_argument, NULL, COPY_FROM_OPTION},
	{NULL, 0, NULL, 0}
};

int flags; /* used input method */

static struct cgroup *copy_name_value_from_cgroup(char src_cg_path[FILENAME_MAX])
{
	struct cgroup *src_cgroup;
	int ret = 0;

	/* create source cgroup */
	src_cgroup = cgroup_new_cgroup(src_cg_path);
	if (!src_cgroup) {
		err("can't create cgroup: %s\n", cgroup_strerror(ECGFAIL));
		goto scgroup_err;
	}

	/* copy the name-version values to the cgroup structure */
	ret = cgroup_get_cgroup(src_cgroup);
	if (ret != 0) {
		err("cgroup %s error: %s\n", src_cg_path, cgroup_strerror(ret));
		goto scgroup_err;
	}

	return src_cgroup;

scgroup_err:
	cgroup_free(&src_cgroup);

	return NULL;
}

static void usage(int status, const char *program_name)
{
	if (status != 0) {
		err("Wrong input parameters, ");
		err("try %s --help' for more information.\n", program_name);
		return;
	}

	info("Usage: %s [-r <name=value>] <cgroup_path> ...\n", program_name);
	info("   or: %s --copy-from <source_cgroup_path> <cgroup_path> ...\n", program_name);
	info("Set the parameters of given cgroup(s)\n");
	info("  -r, --variable <name>			Define parameter to set\n");
	info("  --copy-from <source_cgroup_path>	Control group whose ");
	info("parameters will be copied\n");
#ifdef WITH_SYSTEMD
	info("  -b					Ignore default systemd ");
	info("delegate hierarchy\n");
#endif
}
#endif /* !UNIT_TEST */

STATIC int parse_r_flag(const char * const program_name, const char * const name_value_str,
			struct control_value * const name_value)
{
	char *copy = NULL, *buf = NULL;
	int ret = 0;

	buf = strchr(name_value_str, '=');
	if (buf == NULL) {
		err("%s: wrong parameter of option -r: %s\n", program_name, optarg);
		ret = EXIT_BADARGS;
		goto err;
	}

	copy = strdup(name_value_str);
	if (copy == NULL) {
		err("%s: not enough memory\n", program_name);
		ret = -1;
		goto err;
	}

	/* parse optarg value */
	buf = strtok(copy, "=");
	if (buf == NULL) {
		err("%s: wrong parameter of option -r: %s\n", program_name, optarg);
		ret = EXIT_BADARGS;
		goto err;
	}

	strncpy(name_value->name, buf, FILENAME_MAX);
	name_value->name[FILENAME_MAX-1] = '\0';

	buf = strchr(name_value_str, '=');
	/*
	 * we don't need to check the return value of strchr because we
	 * know there's already an '=' character in the string.
	 */
	buf++;

	if (strlen(buf) == 0) {
		err("%s: wrong parameter of option -r: %s\n", program_name, optarg);
		ret = EXIT_BADARGS;
		goto err;
	}

	strncpy(name_value->value, buf, CG_CONTROL_VALUE_MAX);
	name_value->value[CG_CONTROL_VALUE_MAX-1] = '\0';

err:
	if (copy)
		free(copy);

	return ret;
}

#ifndef UNIT_TEST
int main(int argc, char *argv[])
{
	int ignore_default_systemd_delegate_slice = 0;
	struct control_value *name_value = NULL;
	int nv_number = 0;
	int nv_max = 0;

	char src_cg_path[FILENAME_MAX] = "\0";
	struct cgroup *src_cgroup = NULL;
	struct cgroup *cgroup = NULL;

	int ret = 0;
	int c;

	/* no parameter on input */
	if (argc < 2) {
		err("Usage is %s -r <name=value> relative path to cgroup>\n", argv[0]);
		exit(EXIT_BADARGS);
	}

	/* parse arguments */
#ifdef WITH_SYSTEMD
	while ((c = getopt_long (argc, argv, "r:hb", long_options, NULL)) != -1) {
		switch (c) {
		case 'b':
			ignore_default_systemd_delegate_slice = 1;
			break;
#else
	while ((c = getopt_long (argc, argv, "r:h", long_options, NULL)) != -1) {
		switch (c) {
#endif
		case 'h':
			usage(0, argv[0]);
			ret = 0;
			goto err;

		case 'r':
			if ((flags &  FL_COPY) != 0) {
				usage(1, argv[0]);
				ret = EXIT_BADARGS;
				goto err;
			}
			flags |= FL_RULES;

			/* add name-value pair to buffer (= name_value variable) */
			if (nv_number >= nv_max) {
				nv_max += CG_NV_MAX;
				name_value = (struct control_value *)
					realloc(name_value, nv_max * sizeof(struct control_value));
				if (!name_value) {
					err("%s: not enough memory\n", argv[0]);
					ret = -1;
					goto err;
				}
			}

			ret = parse_r_flag(argv[0], optarg, &name_value[nv_number]);
			if (ret)
				goto err;

			nv_number++;
			break;
		case COPY_FROM_OPTION:
			if (flags != 0) {
				usage(1, argv[0]);
				ret = EXIT_BADARGS;
				goto err;
			}
			flags |= FL_COPY;
			strncpy(src_cg_path, optarg, FILENAME_MAX);
			src_cg_path[FILENAME_MAX-1] = '\0';
			break;
		default:
			usage(1, argv[0]);
			ret = EXIT_BADARGS;
			goto err;
		}
	}

	/* no cgroup name */
	if (!argv[optind]) {
		err("%s: no cgroup specified\n", argv[0]);
		ret = EXIT_BADARGS;
		goto err;
	}

	if (flags == 0) {
		err("%s: no name-value pair was set\n", argv[0]);
		ret = EXIT_BADARGS;
		goto err;
	}

	/* initialize libcgroup */
	ret = cgroup_init();
	if (ret) {
		err("%s: libcgroup initialization failed: %s\n", argv[0], cgroup_strerror(ret));
		goto err;
	}

	/* this is false always for disable-systemd */
	if (!ignore_default_systemd_delegate_slice)
		cgroup_set_default_systemd_cgroup();

	/* copy the name-value pairs from -r options */
	if ((flags & FL_RULES) != 0) {
		src_cgroup = create_cgroup_from_name_value_pairs("tmp", name_value, nv_number);
		if (src_cgroup == NULL)
			goto err;
	}

	/* copy the name-value from the given group */
	if ((flags & FL_COPY) != 0) {
		src_cgroup = copy_name_value_from_cgroup(src_cg_path);
		if (src_cgroup == NULL)
			goto err;
	}

	while (optind < argc) {
		/* create new cgroup */
		cgroup = cgroup_new_cgroup(argv[optind]);
		if (!cgroup) {
			ret = ECGFAIL;
			err("%s: can't add new cgroup: %s\n", argv[0], cgroup_strerror(ret));
			goto cgroup_free_err;
		}

		/* copy the values from the source cgroup to new one */
		ret = cgroup_copy_cgroup(cgroup, src_cgroup);
		if (ret != 0) {
			err("%s: cgroup %s error: %s\n", argv[0], src_cg_path,
			    cgroup_strerror(ret));
			goto cgroup_free_err;
		}

		/* modify cgroup based on values of the new one */
		ret = cgroup_modify_cgroup(cgroup);
		if (ret) {
			err("%s: cgroup modify error: %s\n", argv[0], cgroup_strerror(ret));
			goto cgroup_free_err;
		}

		optind++;
		cgroup_free(&cgroup);
	}

cgroup_free_err:
	if (cgroup)
		cgroup_free(&cgroup);
	if (src_cgroup)
		cgroup_free(&src_cgroup);
err:
	free(name_value);

	return ret;
}
#endif /* !UNIT_TEST */
