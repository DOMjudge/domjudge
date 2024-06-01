// SPDX-License-Identifier: LGPL-2.1-only
/**
 * Copyright (C) 2010 Red Hat, Inc. All Rights Reserved.
 * Written by Ivana Hutarova Varekova <varekova@redhat.com>
 */

#include "tools-common.h"

#include <libcgroup.h>
#include <libcgroup-internal.h>

#include <unistd.h>
#include <stdlib.h>
#include <string.h>
#include <getopt.h>
#include <stdio.h>
#include <errno.h>
#include <pwd.h>
#include <grp.h>

#include <sys/types.h>
#include <sys/stat.h>

enum flag {
	FL_LIST =	1,
	FL_SILENT =	2,  /* do-not show any warning/error output */
	FL_STRICT =	4,  /* don show the variables which are not on allowlist */
	FL_OUTPUT =	8,  /* output should be redirect to the given file */
	FL_DENY =	16, /* denylist set */
	FL_ALLOW =	32, /* allowlist set */
};

#define DENYLIST_CONF	"/etc/cgsnapshot_denylist.conf"
#define ALLOWLIST_CONF	"/etc/cgsnapshot_allowlist.conf"

struct deny_list_type {
	char *name;			/* variable name */
	struct deny_list_type *next;	/* pointer to the next record */
};

struct deny_list_type *deny_list;
struct deny_list_type *allow_list;

typedef char cont_name_t[FILENAME_MAX];

int flags;
FILE *output_f;

/*
 * Display the usage
 */
static void usage(int status, const char *program_name)
{
	if (status != 0) {
		err("Wrong input parameters, try %s -h' for more information.\n", program_name);
		return;
	}

	info("Usage: %s [-h] [-s] [-b FILE] [-w FILE] [-f FILE] [controller] [...]\n",
	     program_name);
	info("Generate the configuration file for given controllers\n");
	info("  -b, --denylist=FILE		Set the denylist");
	info(" configuration file (default %s)\n", DENYLIST_CONF);
	info("  -f, --file=FILE		Redirect the output to output_file\n");
	info("  -h, --help			Display this help\n");
	info("  -s, --silent			Ignore all warnings\n");
	info("  -t, --strict			Don't show variables ");
	info("which are not on the allowlist\n");
	info("  -w, --allowlist=FILE		Set the allowlist ");
	info("configuration file (don't used by default)\n");
}

/* cache values from denylist file to the list structure */
int load_list(char *filename, struct deny_list_type **p_list)
{
	struct deny_list_type *start = NULL;
	struct deny_list_type *end = NULL;
	struct deny_list_type *new;

	char buf[FILENAME_MAX];
	char name[FILENAME_MAX];
	int i = 0;
	FILE *fw;
	int ret;

	fw = fopen(filename, "r");
	if (fw == NULL) {
		err("ERROR: Failed to open file %s: %s\n", filename, strerror(errno));
		*p_list = NULL;
		return 1;
	}

	/* go through the configuration file and search the line */
	while (fgets(buf, FILENAME_MAX, fw) != NULL) {
		buf[FILENAME_MAX-1] = '\0';
		i = 0;

		/* if the record start with # then skip it */
		while ((buf[i] == ' ') || (buf[i] == '\n'))
			i++;

		if ((buf[i] == '#') || (buf[i] == '\0'))
			continue;

		ret = sscanf(buf, "%s", name);
		if (ret == 0)
			continue;

		new = (struct deny_list_type *) malloc(sizeof(struct deny_list_type));
		if (new == NULL) {
			err("ERROR: Memory allocation problem (%s)\n", strerror(errno));
			ret = 1;
			goto err;
		}

		new->name = strdup(name);
		if (new->name == NULL) {
			err("ERROR: Memory allocation problem (%s)\n", strerror(errno));
			ret = 1;
			free(new);
			goto err;
		}
		new->next = NULL;

		/* update the variables list */
		if (start == NULL) {
			start = new;
			end = new;
		} else {
			end->next = new;
			end = new;
		}
	}
	fclose(fw);

	*p_list = start;

	return 0;

err:
	fclose(fw);
	new = start;
	while (new != NULL) {
		end = new->next;
		free(new->name);
		free(new);
		new = end;
	}
	*p_list = NULL;

	return ret;
}

/* free list structure */
void free_list(struct deny_list_type *list)
{
	struct deny_list_type *now;
	struct deny_list_type *next;

	now = list;
	while (now != NULL) {
		next = now->next;
		free(now->name);
		free(now);
		now = next;
	}
}

/*
 * Test whether the variable is on the list return values are:
 * 1 ... was found
 * 0 ... no record was found
 */
int is_on_list(char *name, struct deny_list_type *list)
{
	struct deny_list_type *record;

	record = list;
	/* go through the list of all values */
	while (record != NULL) {
		/* if the variable name is found */
		if (strcmp(record->name, name) == 0) {
			return 1; /* return its value */
		}
		record = record->next;
	}

	return 0; /* the variable was not found */
}

/*
 * Display permissions record for the given group defined by path
 */
static int display_permissions(const char *path, const char * const cg_name,
			       const char * const ctrl_name)
{
	char tasks_path[FILENAME_MAX];
	struct passwd *pw;
	struct group *gr;
	struct stat sba;
	struct stat sbt;
	int ret;

	/* admin permissions record */
	/* get the directory statistic */
	ret = stat(path, &sba);
	if (ret) {
		err("ERROR: can't read statistics about %s\n", path);
		return -1;
	}

	/* tasks permissions record */
	/* get tasks file statistic */
	ret = cgroup_build_tasks_procs_path(tasks_path, sizeof(tasks_path), cg_name, ctrl_name);
	if (ret) {
		err("ERROR: can't build tasks/procs path: %d\n", ret);
		return -1;
	}

	ret = stat(tasks_path, &sbt);
	if (ret) {
		err("ERROR: can't read statistics about %s\n", tasks_path);
		return -1;
	}

	if ((sba.st_uid) || (sba.st_gid) || (sbt.st_uid) || (sbt.st_gid)) {
		/*
		 * some uid or gid is nonroot, admin permission
		 * tag is necessary
		 */

		/* print the header */
		fprintf(output_f, "\tperm {\n");

		/* find out the user and group name */
		pw = getpwuid(sba.st_uid);
		if (pw == NULL) {
			err("ERROR: can't get %d user name\n", sba.st_uid);
			fprintf(output_f, "}\n}\n");
			return -1;
		}

		gr = getgrgid(sba.st_gid);
		if (gr == NULL) {
			err("ERROR: can't get %d group name\n", sba.st_gid);
			fprintf(output_f, "}\n}\n");
			return -1;
		}

		/* print the admin record */
		fprintf(output_f, "\t\tadmin {\n");
		fprintf(output_f, "\t\t\tuid = %s;\n", pw->pw_name);
		fprintf(output_f, "\t\t\tgid = %s;\n", gr->gr_name);
		fprintf(output_f, "\t\t}\n");

		/* find out the user and group name */
		pw = getpwuid(sbt.st_uid);
		if (pw == NULL) {
			err("ERROR: can't get %d user name\n", sbt.st_uid);
			fprintf(output_f, "}\n}\n");
			return -1;
		}

		gr = getgrgid(sbt.st_gid);
		if (gr == NULL) {
			err("ERROR: can't get %d group name\n", sbt.st_gid);
			fprintf(output_f, "}\n}\n");
			return -1;
		}

		/* print the task record */
		fprintf(output_f, "\t\ttask {\n");
		fprintf(output_f, "\t\t\ttuid = %s;\n", pw->pw_name);
		fprintf(output_f, "\t\t\ttgid = %s;\n", gr->gr_name);
		fprintf(output_f, "\t\t}\n");

		fprintf(output_f, "\t}\n");
	}

	return 0;
}

/*
 * Display the control group record:
 * header
 *   permission record
 *   controllers records
 * tail
 */
static int display_cgroup_data(struct cgroup *group,
			       char controller[CG_CONTROLLER_MAX][FILENAME_MAX],
			       const char *group_path, int root_path_len, int first,
			       const char *program_name)
{
	struct cgroup_controller *group_controller = NULL;
	char var_path[FILENAME_MAX];
	char *value = NULL;
	char *output_name;
	struct stat sb;
	int bl, wl = 0; /* is on the denylist/allowlist flag */
	int nr_var = 0;
	int i = 0, j;
	int ret = 0;
	char *name;

	/* print the  group definition header */
	fprintf(output_f, "group %s {\n", group->name);

	/* for all wanted controllers display controllers tag */
	while (controller[i][0] != '\0') {
		/* display the permission tags */
		ret = display_permissions(group_path, group->name, controller[i]);
		if (ret)
			return ret;

		group_controller = cgroup_get_controller(group, controller[i]);
		if (group_controller == NULL) {
			info("cannot find controller '%s' in group '%s'\n", controller[i],
			     group->name);
			i++;
			ret = -1;
			continue;
		}

		/* print the controller header */
		if (strncmp(controller[i], "name=", 5) == 0)
			fprintf(output_f, "\t\"%s\" {\n", controller[i]);
		else
			fprintf(output_f, "\t%s {\n", controller[i]);
		i++;
		nr_var = cgroup_get_value_name_count(group_controller);

		for (j = 0; j < nr_var; j++) {
			name = cgroup_get_value_name(group_controller, j);

			/*
			 * For the non-root groups cgconfigparser set
			 * permissions of variable files to 777. Thus it
			 * is necessary to test the permissions of variable
			 * files in the root group to find out whether the
			 * variable is writable.
			 */
			if (root_path_len >= FILENAME_MAX)
				root_path_len = FILENAME_MAX - 1;

			strncpy(var_path, group_path, root_path_len);
			var_path[root_path_len] = '\0';

			strncat(var_path, "/", FILENAME_MAX - strlen(var_path) - 1);
			var_path[FILENAME_MAX-1] = '\0';

			strncat(var_path, name,	FILENAME_MAX - strlen(var_path) - 1);
			var_path[FILENAME_MAX-1] = '\0';

			/* test whether the  write permissions */
			ret = stat(var_path, &sb);
			/*
			 * freezer.state is not in root group so ret != 0,
			 * but it should be listed device.list should be
			 * read to create device.allow input
			 */
			/* 0200 == S_IWUSR */
			if ((ret == 0) && ((sb.st_mode & 0200) == 0) &&
			    (strcmp("devices.list", name) != 0)) {
				/* variable is not writable */
				continue;
			}

			/*
			 * find whether the variable is denylisted
			 * or allowlisted
			 */
			bl = is_on_list(name, deny_list);
			wl = is_on_list(name, allow_list);

			/* if it is denylisted skip it and continue */
			if (bl)
				continue;

			/*
			 * if it is not allowlisted and strict tag is used
			 * skip it and continue too
			 */
			if ((!wl) && (flags &  FL_STRICT))
				continue;

			/*
			 * if it is not allowlisted and silent tag is not
			 * used write an warning
			 */
			if ((!wl) && !(flags &  FL_SILENT) && (first)) {
				err("WARNING: variable %s is neither ", name);
				err("deny nor allow list\n");
			}

			output_name = name;

			/*
			 * deal with devices variables:
			 * - omit devices.deny and device.allow,
			 * - generate devices.{deny,allow} from
			 * device.list variable (deny all and then
			 * all device.list devices
			 */
			if ((strcmp("devices.deny", name) == 0) ||
			    (strcmp("devices.allow", name) == 0))
				continue;

			if (strcmp("devices.list", name) == 0) {
				output_name = "devices.allow";
				fprintf(output_f, "\t\tdevices.deny=\"a *:* rwm\";\n");
			}

			ret = cgroup_get_value_string(group_controller, name, &value);

			/* variable can not be read */
			if (ret != 0) {
				ret = 0;
				err("ERROR: Value of variable %s can be read\n", name);
				goto err;
			}
			fprintf(output_f, "\t\t%s=\"%s\";\n", output_name, value);
			free(value);
		}
		fprintf(output_f, "\t}\n");
	}

	/* tail of the record */
	fprintf(output_f, "}\n\n");

err:
	return ret;
}

/*
 * creates the record about the hierarchies which contains
 * "controller" subsystem
 */
static int display_controller_data(char controller[CG_CONTROLLER_MAX][FILENAME_MAX],
				   const char *program_name)
{
	char cgroup_name[FILENAME_MAX];
	struct cgroup_file_info info;
	struct cgroup *group = NULL;
	int prefix_len;
	void *handle;
	int first = 1;
	int lvl;
	int ret;

	/*
	 * start to parse the structure for the first controller -
	 * controller[0] attached to hierarchy
	 */
	ret = cgroup_walk_tree_begin(controller[0], "/", 0, &handle, &info, &lvl);
	if (ret != 0)
		return ret;

	prefix_len = strlen(info.full_path);

	/* go through all files and directories */
	while ((ret = cgroup_walk_tree_next(0, &handle, &info, lvl)) == 0) {
		/* some group starts here */
		if (info.type == CGROUP_FILE_TYPE_DIR) {
			/* parse the group name from full_path*/
			strncpy(cgroup_name, &info.full_path[prefix_len], FILENAME_MAX);
			cgroup_name[FILENAME_MAX-1] = '\0';

			/* start to grab data about the new group */
			group = cgroup_new_cgroup(cgroup_name);
			if (group == NULL) {
				info("cannot create group '%s'\n", cgroup_name);
				ret = ECGFAIL;
				goto err;
			}

			ret = cgroup_get_cgroup(group);
			if (ret != 0) {
				/*
				 * We know for sure that the cgroup exists
				 * but just that the cgroup v2 controller
				 * is not enabled in the cgroup.subtree_control
				 * file.
				 */
				if (ret != ECGROUPNOTEXIST) {
					info("cannot read group '%s': %s %d\n", cgroup_name,
					     cgroup_strerror(ret), ret);
					goto err;
				}
			}

			if (ret == 0)
				display_cgroup_data(group, controller, info.full_path, prefix_len,
						    first, program_name);
			first = 0;
			cgroup_free(&group);
		}
	}

err:
	cgroup_walk_tree_end(&handle);
	if (ret == ECGEOF)
		ret = 0;

	return ret;
}

static int is_ctlr_on_list(char controllers[CG_CONTROLLER_MAX][FILENAME_MAX],
			   cont_name_t wanted_conts[CG_CONTROLLER_MAX])
{
	char tmp_controllers[CG_CONTROLLER_MAX][FILENAME_MAX];
	int i = 0, j = 0, k = 0;
	int ret = 0;

	while (controllers[i][0] != '\0') {
		while (wanted_conts[j][0] != '\0') {
			if (strcmp(controllers[i], wanted_conts[j]) == 0) {
				strncpy(tmp_controllers[k], wanted_conts[j], FILENAME_MAX - 1);
				(tmp_controllers[k])[FILENAME_MAX - 1] = '\0';
				k++;
			}
			j++;
		}
		j = 0;
		i++;
	}

	(tmp_controllers[k])[0] = '\0';

	/* Lets reset the controllers to intersection of controller ∩ wanted_conts */
	for (i = 0; tmp_controllers[i][0] != '\0'; i++) {
		/*
		 * gcc complains about truncation when using snprintf() and
		 * and coverity complains about truncation when using strncpy().
		 * Avoid both these warnings by directly invoking memcpy()
		 */
		memcpy(controllers[i], tmp_controllers[i], sizeof(controllers[i]));
		ret = 1;
	}
	(controllers[i])[0] = '\0';

	return ret;
}


/* print data about input cont_name controller */
static int parse_controllers(cont_name_t cont_names[CG_CONTROLLER_MAX], const char *program_name)
{
	char controllers[CG_CONTROLLER_MAX][FILENAME_MAX] = {"\0"};
	struct cgroup_mount_point controller;
	char path[FILENAME_MAX];
	void *handle;
	int ret = 0;
	int max = 0;

	path[0] = '\0';

	ret = cgroup_get_controller_begin(&handle, &controller);

	/* go through the list of controllers/mount point pairs */
	while (ret == 0) {
		if (strcmp(path, controller.path) == 0) {
			/*
			 * if it is still the same mount point
			 *
			 * note that the last entry in controllers[][] must be '\0', so
			 * we need to stop populating the array at CG_CONTROLLER_MAX - 1
			 */
			if (max < CG_CONTROLLER_MAX - 1) {
				strncpy(controllers[max], controller.name, FILENAME_MAX);
				(controllers[max])[FILENAME_MAX-1] = '\0';
				max++;
			}
		} else {
			/* we got new mount point, print it if needed */
			if ((!(flags & FL_LIST) || (is_ctlr_on_list(controllers, cont_names))) &&
			    (max != 0 && max < CG_CONTROLLER_MAX)) {
				(controllers[max])[0] = '\0';
				ret = display_controller_data(controllers, program_name);
				if (ret)
					goto err;
			}

			strncpy(controllers[0], controller.name, FILENAME_MAX);
			(controllers[0])[FILENAME_MAX-1] = '\0';

			strncpy(path, controller.path, FILENAME_MAX);
			path[FILENAME_MAX-1] = '\0';
			max = 1;
		}

		/* the actual controller should not be printed */
		ret = cgroup_get_controller_next(&handle, &controller);
	}

	if ((!(flags & FL_LIST) || (is_ctlr_on_list(controllers, cont_names))) &&
	     (max != 0 && max < CG_CONTROLLER_MAX)) {
		(controllers[max])[0] = '\0';
		ret = display_controller_data(controllers, program_name);
	}

err:
	cgroup_get_controller_end(&handle);
	if (ret != ECGEOF)
		return ret;

	return 0;
}

static int show_mountpoints(const char *controller)
{
	char path[FILENAME_MAX];
	int quote = 0;
	void *handle;
	int ret;

	if (strncmp(controller, "name=", 5) == 0)
		quote = 1;

	ret = cgroup_get_subsys_mount_point_begin(controller, &handle, path);
	if (ret)
		return ret;

	while (ret == 0) {
		if (quote)
			fprintf(output_f, "\t\"%s\" = %s;\n", controller, path);
		else
			fprintf(output_f, "\t%s = %s;\n", controller, path);
		ret = cgroup_get_subsys_mount_point_next(&handle, path);
	}
	cgroup_get_subsys_mount_point_end(&handle);

	if (ret != ECGEOF)
		return ret;

	return 0;
}

/*
 * parse whether data about given controller "name" should be displayed.
 * If yes then the data are printed. "cont_names" is list of controllers
 * which should be shown.
 */
static void parse_mountpoint(cont_name_t cont_names[CG_CONTROLLER_MAX], char *name)
{
	int i;

	/* if there is no controller list show all mounted controllers */
	if (!(flags & FL_LIST)) {
		if (show_mountpoints(name)) {
			/* the controller is not mounted */
			if ((flags & FL_SILENT) == 0)
				err("ERROR: %s hierarchy not mounted\n", name);
		}
		return;
	}

	/* there is controller list - show wanted mounted controllers only */
	for (i = 0; i <= CG_CONTROLLER_MAX-1; i++) {
		if (!strncmp(cont_names[i], name, strlen(name)+1)) {
			/* controller is on the list */
			if (show_mountpoints(name)) {
				/* the controller is not mounted */
				if ((flags & FL_SILENT) == 0) {
					err("ERROR: %s hierarchy not mounted\n", name);
				}
			break;
			}
		break;
		}
	}
}

/* print data about input mount points */
static int parse_mountpoints(cont_name_t cont_names[CG_CONTROLLER_MAX], const char *program_name)
{
	struct cgroup_mount_point mount;
	struct controller_data info;
	int ret, final_ret = 0;
	void *handle;

	/* start mount section */
	fprintf(output_f, "mount {\n");

	/* go through the controller list */
	ret = cgroup_get_all_controller_begin(&handle, &info);
	while (ret == 0) {

		/* the controller attached to some hierarchy */
		if  (info.hierarchy != 0)
			parse_mountpoint(cont_names, info.name);

		/* next controller */
		ret = cgroup_get_all_controller_next(&handle, &info);
	}

	if (ret != ECGEOF) {
		if ((flags &  FL_SILENT) != 0) {
			err("E: in get next controller %s\n", cgroup_strerror(ret));
		}
		final_ret = ret;
	}

	cgroup_get_all_controller_end(&handle);

	/* process also named hierarchies */
	ret = cgroup_get_controller_begin(&handle, &mount);
	while (ret == 0) {
		if (strncmp(mount.name, "name=", 5) == 0)
			parse_mountpoint(cont_names, mount.name);
		ret = cgroup_get_controller_next(&handle, &mount);
	}

	if (ret != ECGEOF) {
		if ((flags &  FL_SILENT) != 0) {
			err("E: in get next controller %s\n", cgroup_strerror(ret));
		}
		final_ret = ret;
	}

	cgroup_get_controller_end(&handle);

	/* finish mount section */
	fprintf(output_f, "}\n\n");

	return final_ret;
}

int main(int argc, char *argv[])
{
	static struct option long_opts[] = {
		{"help",	      no_argument, NULL, 'h'},
		{"silent",	      no_argument, NULL, 's'},
		{"denylist",	required_argument, NULL, 'b'},
		{"allowlist",	required_argument, NULL, 'w'},
		{"strict",	      no_argument, NULL, 't'},
		{"file",	required_argument, NULL, 'f'},
		{0, 0, 0, 0}
	};

	cont_name_t wanted_cont[CG_CONTROLLER_MAX];
	char bl_file[FILENAME_MAX];  /* denylist file name */
	char wl_file[FILENAME_MAX];  /* allowlist file name */
	int ret = 0, err;
	int c_number = 0;
	int c, i;

	for (i = 0; i < CG_CONTROLLER_MAX; i++)
		wanted_cont[i][0] = '\0';

	flags = 0;

	/* parse arguments */
	while ((c = getopt_long(argc, argv, "hsb:w:tf:", long_opts, NULL)) > 0) {
		switch (c) {
		case 'h':
			usage(0, argv[0]);
			return 0;
		case 's':
			flags |= FL_SILENT;
			break;
		case 'b':
			flags |= FL_DENY;
			strncpy(bl_file, optarg, FILENAME_MAX);
			bl_file[FILENAME_MAX-1] = '\0';
			break;
		case 'w':
			flags |= FL_ALLOW;
			strncpy(wl_file, optarg, FILENAME_MAX);
			wl_file[FILENAME_MAX-1] = '\0';
			break;
		case 't':
			flags |= FL_STRICT;
			break;
		case 'f':
			flags |= FL_OUTPUT;
			output_f = fopen(optarg, "w");
			if (output_f == NULL) {
				err("%s: Failed to open file %s\n", argv[0], optarg);
				return ECGOTHER;
			}
			break;
		default:
			usage(1, argv[0]);
			exit(EXIT_BADARGS);
		}
	}

	/* read the list of controllers */
	while (optind < argc) {
		flags |= FL_LIST;
		strncpy(wanted_cont[c_number], argv[optind], FILENAME_MAX);
		(wanted_cont[c_number])[FILENAME_MAX-1] = '\0';
		c_number++;
		optind++;
		if (optind == CG_CONTROLLER_MAX-1) {
			err("too many parameters\n");
			break;
		}
	}

	if ((flags & FL_OUTPUT) == 0)
		output_f = stdout;

	/* denylist */
	if (flags & FL_DENY) {
		ret  = load_list(bl_file, &deny_list);
	} else {
		/* load the denylist from the default location */
		ret  = load_list(DENYLIST_CONF, &deny_list);
	}
	if (ret != 0) {
		ret = EXIT_BADARGS;
		goto finish;
	}

	/* allowlist */
	if (flags & FL_ALLOW)
		ret = load_list(wl_file, &allow_list);
	if (ret != 0) {
		ret = EXIT_BADARGS;
		goto finish;
	}

	/* print the header */
	fprintf(output_f, "# Configuration file generated by cgsnapshot\n");

	/* initialize libcgroup */
	ret = cgroup_init();
	if (ret)
		/* empty configuration file */
		goto finish;

	/* print mount points section */
	ret = parse_mountpoints(wanted_cont, argv[0]);
	ret = ret ? EXIT_BADARGS : 0;
	/* continue with processing on error*/

	/* print hierarchies section */
	/* replace error from parse_mountpoints() only with another error */
	err = parse_controllers(wanted_cont, argv[0]);
	if (err)
		ret = err;

finish:
	free_list(deny_list);
	free_list(allow_list);

	if (output_f != stdout)
		fclose(output_f);

	return ret;
}
