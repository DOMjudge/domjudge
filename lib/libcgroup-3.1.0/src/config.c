// SPDX-License-Identifier: LGPL-2.1-only
/**
 * Copyright IBM Corporation. 2007
 *
 * Authors:	Balbir Singh <balbir@linux.vnet.ibm.com>
 *		Dhaval Giani <dhaval@linux.vnet.ibm.com>
 *
 * TODOs:
 *	1. Implement our own hashing scheme
 *
 * Code initiated and designed by Balbir Singh. All faults are most likely
 * his mistake.
 *
 * Cleanup and changes to use the "official" structures and functions made
 * by Dhaval Giani. All faults will still be Balbir's mistake :)
 */

#include "tools/tools-common.h"

#include <libcgroup.h>
#include <libcgroup-internal.h>
#include <libcgroup/systemd.h>

#include <pthread.h>
#include <assert.h>
#include <dirent.h>
#include <limits.h>
#include <search.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <errno.h>
#include <stdio.h>
#include <pwd.h>
#include <grp.h>

#include <sys/mount.h>
#include <sys/stat.h>
#include <sys/types.h>


unsigned int MAX_CGROUPS = 64;	/* NOTE: This value changes dynamically */
unsigned int MAX_TEMPLATES = 64;/* NOTE: This value changes dynamically */

enum tc_switch_t {
	CGROUP,
	TEMPLATE,
};

extern FILE *yyin;
extern int yyparse(void);

static struct cgroup default_group;
static int default_group_set;

/*
 * The basic global data structures.
 *
 * config_mount_table -> Where what controller is mounted
 * table_index -> Where in the table are we.
 * config_table_lock -> Serializing access to config_mount_table.
 * cgroup_table -> Which cgroups have to be created.
 * cgroup_table_index -> Where in the cgroup_table we are.
 */
static struct cg_mount_table_s config_mount_table[CG_CONTROLLER_MAX];
static struct cg_mount_table_s config_namespace_table[CG_CONTROLLER_MAX];
static int config_table_index;
static int namespace_table_index;
static pthread_rwlock_t config_table_lock = PTHREAD_RWLOCK_INITIALIZER;
static pthread_rwlock_t namespace_table_lock = PTHREAD_RWLOCK_INITIALIZER;
static struct cgroup *config_cgroup_table;
static int cgroup_table_index;

/*
 * template structures filled by cgroup_parse_config when the configuration
 * file is parsing (analogous to config_cgroup_table and cgroup_table_index
 * for cgroups)
 */
static struct cgroup *config_template_table;
static int config_template_table_index;

/*
 * template structures used for templates cache, config_template_table and
 * cgroup_template_table_index are rewritten in each cgroup_parse_config
 * thus not only if we want to reload template cache
 */
static struct cgroup *template_table;
static int template_table_index;
static struct cgroup_string_list *template_files;


/* Needed for the type while mounting cgroupfs. */
#define CGROUP_FILESYSTEM "cgroup"

#ifdef WITH_SYSTEMD
/* Directory that holds dynamically created internal libcgroup files */
static const char * const systemd_def_cgrp_file_dir = "/var/run/libcgroup/";

/* File that stores the relative delegated systemd cgroup path */
static const char * const systemd_default_cgroup_file = "/var/run/libcgroup/systemd";

/* Lock that protects systemd_default_cgroup_file */
static pthread_rwlock_t systemd_default_cgroup_lock = PTHREAD_RWLOCK_INITIALIZER;

/*
 * cgroup_systemd_opts is dynamically allocated, hence having head
 * and tail helps in traversing.
 */
static struct cgroup_systemd_opts *cgroup_systemd_opts_head = NULL;
static struct cgroup_systemd_opts *cgroup_systemd_opts_tail = NULL;

static int config_create_slice_scope(char * const tmp_systemd_default_cgroup);
#endif

/*
 * NOTE: All these functions return 1 on success and not 0 as is the
 * library convention
 */
/*
 * This call just sets the name of the cgroup. It will always be called in
 * the end, because the parser will work bottom up. It works for cgroup and
 * templates tables based on flag variable:
 * CGROUP ... cgroup
 * TEMPLATE ... template
 */
int config_insert_cgroup(char *cg_name, int flag)
{

	struct cgroup *config_cgroup;
	struct cgroup *config_table;
	unsigned int *max;
	int *table_index;

	switch (flag) {
	case CGROUP:
		table_index = &cgroup_table_index;
		config_table = config_cgroup_table;
		max = &MAX_CGROUPS;
		break;
	case TEMPLATE:
		table_index = &config_template_table_index;
		config_table = config_template_table;
		max = &MAX_TEMPLATES;
		break;
	default:
		return 0;
	}

	if (*table_index >= *max - 1) {
		struct cgroup *newblk;
		unsigned int oldlen;

		if (*max >= INT_MAX) {
			last_errno = ENOMEM;
			return 0;
		}
		oldlen = *max;
		*max *= 2;

		newblk = realloc(config_table, (*max * sizeof(struct cgroup)));
		if (!newblk) {
			last_errno = ENOMEM;
			return 0;
		}

		memset(newblk + oldlen, 0, (*max - oldlen) * sizeof(struct cgroup));
		init_cgroup_table(newblk + oldlen, *max - oldlen);
		config_table = newblk;
		switch (flag) {
		case CGROUP:
			config_cgroup_table = config_table;
			break;
		case TEMPLATE:
			config_template_table = config_table;
			break;
		default:
			return 0;
		}
		cgroup_dbg("maximum %d\n", *max);
		cgroup_dbg("reallocated config_table to %p\n", config_table);
	}

	config_cgroup = &config_table[*table_index];
	strncpy(config_cgroup->name, cg_name, FILENAME_MAX - 1);

	/* Since this will be the last part to be parsed. */
	*table_index = *table_index + 1;
	free(cg_name);

	return 1;
}

/*
 * This call just sets the name of the cgroup. It will always be called in
 * the end, because the parser will work bottom up.
 */
int cgroup_config_insert_cgroup(char *cg_name)
{
	int ret;

	ret = config_insert_cgroup(cg_name, CGROUP);
	return ret;
}

/*
 * This call just sets the name of the template. It will always be called
 * in the end, because the parser will work bottom up.
 */
int template_config_insert_cgroup(char *cg_name)
{
	int ret;

	ret = config_insert_cgroup(cg_name, TEMPLATE);
	return ret;
}

/*
 * This function sets the various controller's control files. It will always
 * append values for config_cgroup/template_table_index entry in the
 * config_cgroup/template_table. The index is incremented in
 * cgroup/template_config_insert_cgroup.
 * flag variable switch between cgroup/templates variables:
 * CGROUP ... cgroup
 * TEMPLATE ... template
 */
int config_parse_controller_options(char *controller, struct cgroup_dictionary *values, int flag)
{
	struct cgroup_controller *cgc;
	struct cgroup *config_cgroup;
	const char *name, *value;
	void *iter = NULL;
	int *table_index;
	int error;

	switch (flag) {
	case CGROUP:
		table_index = &cgroup_table_index;
		config_cgroup =	&config_cgroup_table[*table_index];
		break;
	case TEMPLATE:
		table_index = &config_template_table_index;
		config_cgroup =	&config_template_table[*table_index];
		break;
	default:
		return 0;
	}

	cgroup_dbg("Adding controller %s\n", controller);
	cgc = cgroup_add_controller(config_cgroup, controller);
	if (!cgc)
		goto parse_error;

	/*
	 * Did we just specify the controller to create the correct set of
	 * directories, without setting any values?
	 */
	if (!values)
		goto done;

	error = cgroup_dictionary_iterator_begin(values, &iter, &name, &value);
	while (error == 0) {
		cgroup_dbg("[1] name value pair being processed is %s=%s\n", name, value);
		if (!name)
			goto parse_error;
		error = cgroup_add_value_string(cgc, name, value);
		if (error)
			goto parse_error;
		error = cgroup_dictionary_iterator_next(&iter, &name, &value);
	}
	cgroup_dictionary_iterator_end(&iter);
	iter = NULL;

	if (error != ECGEOF)
		goto parse_error;

done:
	free(controller);

	return 1;

parse_error:
	free(controller);
	cgroup_dictionary_iterator_end(&iter);
	cgroup_delete_cgroup(config_cgroup, 1);
	*table_index = *table_index - 1;

	return 0;
}

/*
 * This function sets the various controller's control files. It will always
 * append values for cgroup_table_index entry in the cgroup_table.
 * The index is incremented in cgroup_config_insert_cgroup
 */

int cgroup_config_parse_controller_options(char *controller, struct cgroup_dictionary *values)
{
	int ret;

	ret = config_parse_controller_options(controller, values, CGROUP);
	return ret;
}

/*
 * This function sets the various controller's control files. It will always
 * append values for config_template_table_index entry in the
 * config_template_table.
 * The index is incremented in template_config_insert_cgroup
 */

int template_config_parse_controller_options(char *controller, struct cgroup_dictionary *values)
{
	int ret;

	ret = config_parse_controller_options(controller, values, TEMPLATE);
	return ret;
}

/*
 * Sets the tasks file's uid and gid for cgroup and templates tables based
 * on flag variable:
 * CGROUP ... cgroup
 * TEMPLATE ... template
 */
int config_group_task_perm(char *perm_type, char *value, int flag)
{
	struct group *group, *group_buffer;
	struct passwd *pw, *pw_buffer;
	char buffer[CGROUP_BUFFER_LEN];
	struct cgroup *config_cgroup;
	long val = atoi(value);
	int table_index;

	switch (flag) {
	case CGROUP:
		table_index = cgroup_table_index;
		config_cgroup = &config_cgroup_table[table_index];
		break;
	case TEMPLATE:
		table_index = config_template_table_index;
		config_cgroup = &config_template_table[table_index];
		break;
	default:
		return 0;
	}

	if (!strcmp(perm_type, "uid")) {
		if (!val) {
			pw = (struct passwd *) malloc(sizeof(struct passwd));
			if (!pw)
				goto group_task_error;

			getpwnam_r(value, pw, buffer, CGROUP_BUFFER_LEN, &pw_buffer);
			if (pw_buffer == NULL) {
				free(pw);
				goto group_task_error;
			}

			val = pw->pw_uid;
			free(pw);
		}
		config_cgroup->tasks_uid = val;
	}

	if (!strcmp(perm_type, "gid")) {
		if (!val) {
			group = (struct group *) malloc(sizeof(struct group));

			if (!group)
				goto group_task_error;

			if (getgrnam_r(value, group, buffer,
				       CGROUP_BUFFER_LEN, &group_buffer) != 0) {
				free(group);
				goto group_task_error;
			}

			val = group->gr_gid;
			free(group);
		}
		config_cgroup->tasks_gid = val;
	}

	if (!strcmp(perm_type, "fperm")) {
		char *endptr;

		val = strtol(value, &endptr, 8);
		if (*endptr)
			goto group_task_error;
		config_cgroup->task_fperm = val;
	}

	free(perm_type);
	free(value);

	return 1;

group_task_error:
	free(perm_type);
	free(value);
	cgroup_delete_cgroup(config_cgroup, 1);
	table_index--;

	return 0;
}

/*
 * Sets the tasks file's uid and gid
 */
int cgroup_config_group_task_perm(char *perm_type, char *value)
{
	int ret;

	ret = config_group_task_perm(perm_type, value, CGROUP);
	return ret;
}

/*
 * Sets the tasks file's uid and gid for templates
 */
int template_config_group_task_perm(char *perm_type, char *value)
{
	int ret;

	ret = config_group_task_perm(perm_type, value, TEMPLATE);
	return ret;
}

/*
 * Sets the admin file's uid and gid for cgroup and templates tables based
 * on flag variable:
 * CGROUP ... cgroup
 * TEMPLATE ... templates
 */
int config_group_admin_perm(char *perm_type, char *value, int flag)
{
	struct group *group, *group_buffer;
	struct passwd *pw, *pw_buffer;
	char buffer[CGROUP_BUFFER_LEN];
	struct cgroup *config_cgroup;
	long val = atoi(value);
	int table_index;

	switch (flag) {
	case CGROUP:
		table_index = cgroup_table_index;
		config_cgroup = &config_cgroup_table[table_index];
		break;
	case TEMPLATE:
		table_index = config_template_table_index;
		config_cgroup = &config_template_table[table_index];
		break;
	default:
		return 0;
	}

	if (!strcmp(perm_type, "uid")) {
		if (!val) {
			pw = (struct passwd *) malloc(sizeof(struct passwd));
			if (!pw)
				goto admin_error;

			getpwnam_r(value, pw, buffer, CGROUP_BUFFER_LEN, &pw_buffer);
			if (pw_buffer == NULL) {
				free(pw);
				goto admin_error;
			}

			val = pw->pw_uid;
			free(pw);
		}
		config_cgroup->control_uid = val;
	}

	if (!strcmp(perm_type, "gid")) {
		if (!val) {
			group = (struct group *) malloc(sizeof(struct group));
			if (!group)
				goto admin_error;

			if (getgrnam_r(value, group, buffer, CGROUP_BUFFER_LEN,
				       &group_buffer) != 0) {
				free(group);
				goto admin_error;
			}

			val = group->gr_gid;
			free(group);
		}
		config_cgroup->control_gid = val;
	}

	if (!strcmp(perm_type, "fperm")) {
		char *endptr;

		val = strtol(value, &endptr, 8);
		if (*endptr)
			goto admin_error;
		config_cgroup->control_fperm = val;
	}

	if (!strcmp(perm_type, "dperm")) {
		char *endptr;

		val = strtol(value, &endptr, 8);
		if (*endptr)
			goto admin_error;
		config_cgroup->control_dperm = val;
	}

	free(perm_type);
	free(value);

	return 1;

admin_error:
	free(perm_type);
	free(value);
	cgroup_delete_cgroup(config_cgroup, 1);
	table_index--;

	return 0;
}

/*
 * Set the control file's uid and gid
 */
int cgroup_config_group_admin_perm(char *perm_type, char *value)
{
	int ret;

	ret = config_group_admin_perm(perm_type, value, CGROUP);
	return ret;
}

/*
 * Set the control file's uid and gid for templates
 */
int template_config_group_admin_perm(char *perm_type, char *value)
{
	int ret;

	ret = config_group_admin_perm(perm_type, value, TEMPLATE);
	return ret;
}

/*
 * The moment we have found the controller's information insert it into
 * the config_mount_table.
 */
int cgroup_config_insert_into_mount_table(char *name, char *mount_point)
{
	int i;

	if (config_table_index >= CG_CONTROLLER_MAX)
		return 0;

	pthread_rwlock_wrlock(&config_table_lock);

	/* Merge controller names with the same mount point */
	for (i = 0; i < config_table_index; i++) {
		if (strcmp(config_mount_table[i].mount.path, mount_point) == 0) {
			char *cname = config_mount_table[i].name;

			strncat(cname, ",", FILENAME_MAX - strlen(cname) - 1);
			strncat(cname, name, FILENAME_MAX - strlen(cname) - 1);
			goto done;
		}
	}

	strncpy(config_mount_table[config_table_index].name, name, CONTROL_NAMELEN_MAX  - 1);
	config_mount_table[config_table_index].name[CONTROL_NAMELEN_MAX - 1] =	'\0';

	strncpy(config_mount_table[config_table_index].mount.path, mount_point, FILENAME_MAX - 1);
	config_mount_table[config_table_index].mount.path[FILENAME_MAX - 1] = '\0';

	config_mount_table[config_table_index].mount.next = NULL;
	config_table_index++;
done:
	pthread_rwlock_unlock(&config_table_lock);
	free(name);
	free(mount_point);

	return 1;
}

/*
 * Cleanup all the data from the config_mount_table
 */
void cgroup_config_cleanup_mount_table(void)
{
	memset(&config_mount_table, 0, sizeof(struct cg_mount_table_s) * CG_CONTROLLER_MAX);
}

/*
 * The moment we have found the controller's information insert it into
 * the config_mount_table.
 */
int cgroup_config_insert_into_namespace_table(char *name, char *nspath)
{
	char *ns_tbl_name, *ns_tbl_path;
	if (namespace_table_index >= CG_CONTROLLER_MAX)
		return 0;

	pthread_rwlock_wrlock(&namespace_table_lock);

	ns_tbl_name = config_namespace_table[namespace_table_index].name;
	strncpy(ns_tbl_name, name, CONTROL_NAMELEN_MAX - 1);
	ns_tbl_name[CONTROL_NAMELEN_MAX - 1 ] = '\0';

	ns_tbl_path = config_namespace_table[namespace_table_index].mount.path;
	strncpy(ns_tbl_path, nspath, FILENAME_MAX - 1);
	ns_tbl_path[FILENAME_MAX - 1] = '\0';

	config_namespace_table[namespace_table_index].mount.next = NULL;
	namespace_table_index++;

	pthread_rwlock_unlock(&namespace_table_lock);
	free(name);
	free(nspath);
	return 1;
}

/*
 * Cleanup all the data from the config_mount_table
 */
void cgroup_config_cleanup_namespace_table(void)
{
	memset(&config_namespace_table, 0, sizeof(struct cg_mount_table_s) * CG_CONTROLLER_MAX);
}

/**
 * Add necessary options for mount. Currently only 'none' option is added
 * for mounts with only 'name=xxx' and without real controller.
 */
static int cgroup_config_ajdust_mount_options(struct cg_mount_table_s *mount, unsigned long *flags)
{
	char *opts = strdup(mount->name);
	char *controller = NULL;
	char *save = NULL;
	int name_only = 1;
	char *token;

	*flags = 0;
	if (opts == NULL)
		return ECGFAIL;

	token = strtok_r(opts, ",", &save);
	while (token != NULL) {
		if (strncmp(token, "name=", 5) != 0) {
			name_only = 0;

			if (!controller) {
				controller = strdup(token);
				if (controller == NULL)
					break;

				strncpy(mount->name, controller, sizeof(mount->name));
				mount->name[sizeof(mount->name)-1] = '\0';
			}

			if (strncmp(token, "nodev", strlen("nodev")) == 0)
				*flags |= MS_NODEV;

			if (strncmp(token, "noexec", strlen("noexec")) == 0)
				*flags |= MS_NOEXEC;

			if (strncmp(token, "nosuid", strlen("nosuid")) == 0)
				*flags |= MS_NOSUID;

		} else if (!name_only) {
			/*
			 * We have controller + name=, do the right thing,
			 * since we are rebuilding mount->name
			 */
			strncat(mount->name, ",", FILENAME_MAX - strlen(mount->name)-1);
			strncat(mount->name, token, FILENAME_MAX - strlen(mount->name) - 1);
		}
		token = strtok_r(NULL, ",", &save);
	}

	free(controller);
	free(opts);

	if (name_only) {
		strncat(mount->name, ",", FILENAME_MAX - strlen(mount->name)-1);
		strncat(mount->name, "none", FILENAME_MAX - strlen(mount->name) - 1);
	}

	return 0;
}

/*
 * Start mounting the mount table
 */
static int cgroup_config_mount_fs(void)
{
	unsigned long flags;
	struct stat buff;
	int error;
	int ret;
	int i;

	for (i = 0; i < config_table_index; i++) {
		struct cg_mount_table_s *curr =	&(config_mount_table[i]);

		ret = stat(curr->mount.path, &buff);
		if (ret < 0 && errno != ENOENT) {
			cgroup_err("cannot access %s: %s\n", curr->mount.path, strerror(errno));
			last_errno = errno;
			error = ECGOTHER;
			goto out_err;
		}

		if (errno == ENOENT) {
			ret = cg_mkdir_p(curr->mount.path);
			if (ret) {
				cgroup_err("cannot create directory %s\n", curr->mount.path);
				error = ret;
				goto out_err;
			}
		} else if (!S_ISDIR(buff.st_mode)) {
			cgroup_err("%s already exists but it is not a directory\n",
				   curr->mount.path);
			errno = ENOTDIR;
			last_errno = errno;
			error = ECGOTHER;
			goto out_err;
		}

		error = cgroup_config_ajdust_mount_options(curr, &flags);
		if (error)
			goto out_err;

		ret = mount(CGROUP_FILESYSTEM, curr->mount.path, CGROUP_FILESYSTEM, flags,
			    curr->name);

		if (ret < 0) {
			cgroup_err("cannot mount %s to %s: %s\n", curr->name, curr->mount.path,
				   strerror(errno));
			error = ECGMOUNTFAIL;
			goto out_err;
		}
	}

	return 0;
out_err:
	/*
	 * If we come here, we have failed. Since we have touched only
	 * mountpoints prior to i, we shall operate on only them now.
	 */
	config_table_index = i;

	return error;
}

static int convert_controller_versions(struct cgroup *cgroup)
{
	enum cg_version_t current_version, convert_version;
	struct cgroup *cgrp_cpy, *convert_cgrp = NULL;
	struct cgroup_controller *cgc;
	int i, j, ret;

	if (!cgroup)
		return ECGINVAL;

	/* no controllers added */
	if (cgroup->index == 0)
		return ECGINVAL;

	cgrp_cpy = cgroup_new_cgroup(cgroup->name);
	if (!cgrp_cpy) {
		cgroup_err("Failed to create cgroup %s\n", cgroup->name);
		return ECGROUPNOTCREATED;
	}

	/* copy the non-controllers details */
	cgrp_cpy->index = 0;

	ret = cgroup_set_uid_gid(cgrp_cpy,
				 cgroup->tasks_uid, cgroup->tasks_gid,
				 cgroup->control_uid, cgroup->control_gid);
	/* cgroup existence is checked, so ignore ret value. */

	cgroup_set_permissions(cgrp_cpy, cgroup->control_dperm,
			       cgroup->control_fperm, cgroup->task_fperm);

	for (i = 0; i < cgroup->index; i++) {
		/*
		 * the controller version settings read from cgconfig.conf,
		 * may mismatch with the system's controller version. Hence
		 * assign the convert version (expected version) to the
		 * system's version of the controller and current version
		 * to the opposite of convert version.
		 */
		convert_version = cgroup->controller[i]->version;
		current_version = convert_version == CGROUP_V1 ? CGROUP_V2 : CGROUP_V1;

		cgc = cgroup_add_controller(cgrp_cpy, cgroup->controller[i]->name);
		if (!cgc) {
			cgroup_err("Failed to allocated controller.\n");
			ret = ECGFAIL;
			goto err;
		}

		for (j = 0; j < cgroup->controller[i]->index; j++) {
			ret = cgroup_add_value_string(cgc,
						      cgroup->controller[i]->values[j]->name,
						      cgroup->controller[i]->values[j]->value);
			if (ret) {
				cgroup_err("Failed to add setting to %s\n", cgc->name);
				goto err;
			}
		}

		/*
		 * There might be more than one controller parsed from the
		 * cgconfig.conf file, let's create cgroup with one controller
		 * and see if it fails and if it does, let's try converting
		 * it and retry the cgroup creation, otherwise move on to
		 * the next controller.
		 */
		ret = cgroup_create_cgroup(cgrp_cpy, 0);
		if (!ret)
			goto next_controller;

		/*
		 * The controller version != cgroup->controller[i] version,
		 * let try to convert the controller to the current version
		 * and retry.
		 */
		convert_cgrp = cgroup_new_cgroup(cgroup->name);
		if (!convert_cgrp) {
			cgroup_err("Failed to created cgroup %s\n", cgroup->name);
			ret = ECGROUPNOTCREATED;
			goto err;
		}
		ret = cgroup_convert_cgroup(convert_cgrp, convert_version, cgrp_cpy,
					    current_version);
		if (ret) {
			cgroup_err("Conversion of controller failed.\n");
			goto err;
		}

		ret = cgroup_create_cgroup(convert_cgrp, 0);
		if (ret) {
			cgroup_err("Failed to create cgroup %s\n", cgroup->name);
			goto err;
		}
		cgroup_free(&convert_cgrp);
next_controller:
		cgroup_free_controller(cgc);
		cgrp_cpy->index = 0;
	}

	ret = 0;
err:
	cgroup_free(&convert_cgrp);
	cgroup_free(&cgrp_cpy);
	return ret;
}
/*
 * Actually create the groups once the parsing has been finished
 */
static int cgroup_config_create_groups(void)
{
	int error = 0;
	int i;

	for (i = 0; i < cgroup_table_index; i++) {
		struct cgroup *cgroup = &config_cgroup_table[i];

		error = cgroup_create_cgroup(cgroup, 0);
		cgroup_dbg("creating group %s, error %d\n", cgroup->name, error);
		if (error) {
			/*
			 * Attempt to convert the controller version
			 * and retry.
			 */
			error = convert_controller_versions(cgroup);
			if (error)
				return error;
		}
	}

	return error;
}

/*
 * Destroy the cgroups
 */
static int cgroup_config_destroy_groups(void)
{
	int error = 0, ret = 0;
	int i;

	for (i = 0; i < cgroup_table_index; i++) {
		struct cgroup *cgroup = &config_cgroup_table[i];

		error = cgroup_delete_cgroup_ext(cgroup, CGFLAG_DELETE_RECURSIVE |
							 CGFLAG_DELETE_IGNORE_MIGRATION);
		if (error)
			/* store the error, but continue deleting the rest */
			ret = error;
	}

	return ret;
}

/*
 * Unmount the controllers
 */
static int cgroup_config_unmount_controllers(void)
{
	int error;
	int i;

	for (i = 0; i < config_table_index; i++) {
		/* We ignore failures and ensure that all mounted containers are unmounted */
		error = umount(config_mount_table[i].mount.path);
		if (error < 0)
			cgroup_dbg("Unmount failed\n");
		error = rmdir(config_mount_table[i].mount.path);
		if (error < 0)
			cgroup_dbg("rmdir failed\n");
	}

	return 0;
}

static int config_validate_namespaces(void)
{
	char *namespace = NULL;
	char *mount_path = NULL;
	int j, subsys_count;
	int error = 0;
	int i;

	pthread_rwlock_wrlock(&cg_mount_table_lock);
	for (i = 0; cg_mount_table[i].name[0] != '\0'; i++) {
		/*
		 * If we get the path in the first run, then we are good,
		 * else we will need to go for two loops. This should be
		 * optimized in the future
		 */
		mount_path = cg_mount_table[i].mount.path;

		/*
		 * Setup the namespace for the subsystems having the same
		 * mount point.
		 */
		if (!cg_namespace_table[i]) {
			namespace = NULL;
		} else {
			namespace = cg_namespace_table[i];
			if (!namespace) {
				last_errno = errno;
				error = ECGOTHER;
				goto out_error;
			}
		}

		/*
		 * We want to handle all the subsytems that are mounted
		 * together. So initialize j to start from the next point
		 * in the mount table.
		 */

		j = i + 1;

		/*
		 * Search through the mount table to locate which subsystems
		 * are mounted together.
		 */
		while (!strncmp(cg_mount_table[j].mount.path, mount_path, FILENAME_MAX)) {
			if (!namespace && cg_namespace_table[j]) {
				/* In case namespace is not setup, set it up */
				namespace = cg_namespace_table[j];
				if (!namespace) {
					last_errno = errno;
					error = ECGOTHER;
					goto out_error;
				}
			}
			j++;
		}
		subsys_count = j;

		/* If there is no namespace, then continue on :) */
		if (!namespace) {
			i = subsys_count -  1;
			continue;
		}

		/*
		 * Validate/setup the namespace
		 * If no namespace is specified, copy the namespace we have
		 * stored. If a namespace is specified, confirm if it is the
		 * same as we have stored. If not, we fail.
		 */
		for (j = i; j < subsys_count; j++) {
			if (!cg_namespace_table[j]) {
				cg_namespace_table[j] = strdup(namespace);
				if (!cg_namespace_table[j]) {
					last_errno = errno;
					error = ECGOTHER;
					goto out_error;
				}
			} else if (strcmp(namespace, cg_namespace_table[j])) {
				error = ECGNAMESPACEPATHS;
				goto out_error;
			}
		}
		/* i++ in the for loop will increment it */
		i = subsys_count - 1;
	}
out_error:
	pthread_rwlock_unlock(&cg_mount_table_lock);

	return error;
}

/*
 * Should always be called after cgroup_init() has been called
 *
 * NOT to be called outside the library. Is handled internally when we are
 * looking to load namespace configurations.
 *
 * This function will order the namespace table in the same fashion as how
 * the mou table is setup.
 *
 * Also it will setup namespaces for all the controllers mounted. In case a
 * controller does not have a namespace assigned to it, it will set it to null.
 */
static int config_order_namespace_table(void)
{
	int error = 0;
	int i = 0;

	pthread_rwlock_wrlock(&cg_mount_table_lock);
	/* Set everything to NULL */
	for (i = 0; i < CG_CONTROLLER_MAX; i++)
		cg_namespace_table[i] = NULL;

	memset(cg_namespace_table, 0, CG_CONTROLLER_MAX * sizeof(cg_namespace_table[0]));

	/* Now fill up the namespace table looking at the table we have otherwise. */
	for (i = 0; i < namespace_table_index; i++) {
		int j;
		int flag = 0;

		for (j = 0; cg_mount_table[j].name[0] != '\0'; j++) {
			if (strncmp(config_namespace_table[i].name, cg_mount_table[j].name,
				    CONTROL_NAMELEN_MAX) == 0) {

				flag = 1;

				if (cg_namespace_table[j]) {
					error = ECGNAMESPACEPATHS;
					goto error_out;
				}

				cg_namespace_table[j] = strdup(
							config_namespace_table[i].mount.path);
				if (!cg_namespace_table[j]) {
					last_errno = errno;
					error = ECGOTHER;
					goto error_out;
				}
			}
		}
		if (!flag) {
			error = ECGNAMESPACECONTROLLER;
			break;
		}
	}
error_out:
	pthread_rwlock_unlock(&cg_mount_table_lock);

	return error;
}

/**
 * Free all memory allocated during cgroup_parse_config(), namely
 * config_cgroup_table and config_template_table.
 */
static void cgroup_free_config(void)
{
	int i;

	if (config_cgroup_table) {
		for (i = 0; i < cgroup_table_index; i++)
			cgroup_free_controllers(&config_cgroup_table[i]);
		free(config_cgroup_table);
		config_cgroup_table = NULL;
	}

	config_table_index = 0;
	if (config_template_table) {
		for (i = 0; i < config_template_table_index; i++)
			cgroup_free_controllers(&config_template_table[i]);

		free(config_template_table);
		config_template_table = NULL;
	}
	config_template_table_index = 0;

	cgroup_cleanup_systemd_opts();
}

/**
 * Applies default permissions/uid/gid to all groups in config file.
 */
static void cgroup_config_apply_default(void)
{
	int i;

	if (config_cgroup_table) {
		for (i = 0; i < cgroup_table_index; i++) {
			struct cgroup *c = &config_cgroup_table[i];

			if (c->control_dperm == NO_PERMS)
				c->control_dperm = default_group.control_dperm;
			if (c->control_fperm == NO_PERMS)
				c->control_fperm = default_group.control_fperm;
			if (c->control_gid == NO_UID_GID)
				c->control_gid = default_group.control_gid;
			if (c->control_uid == NO_UID_GID)
				c->control_uid = default_group.control_uid;
			if (c->task_fperm == NO_PERMS)
				c->task_fperm = default_group.task_fperm;
			if (c->tasks_gid == NO_UID_GID)
				c->tasks_gid = default_group.tasks_gid;
			if (c->tasks_uid == NO_UID_GID)
				c->tasks_uid = default_group.tasks_uid;
		}
	}
}

static int cgroup_parse_config(const char *pathname)
{
	int ret;

	yyin = fopen(pathname, "re");

	if (!yyin) {
		cgroup_err("failed to open file %s\n", pathname);
		last_errno = errno;
		return ECGOTHER;
	}

	config_cgroup_table = calloc(MAX_CGROUPS, sizeof(struct cgroup));
	if (!config_cgroup_table) {
		ret = ECGFAIL;
		goto err;
	}

	config_template_table = calloc(MAX_TEMPLATES, sizeof(struct cgroup));
	if (!config_template_table) {
		ret = ECGFAIL;
		goto err;
	}

	/* Clear all internal variables so this function can be called twice. */
	init_cgroup_table(config_cgroup_table, MAX_CGROUPS);
	init_cgroup_table(config_template_table, MAX_TEMPLATES);
	memset(config_namespace_table, 0, sizeof(config_namespace_table));
	memset(config_mount_table, 0, sizeof(config_mount_table));
	config_table_index = 0;
	namespace_table_index = 0;
	cgroup_table_index = 0;
	config_template_table_index = 0;

	if (!default_group_set) {
		/* init the default cgroup */
		init_cgroup_table(&default_group, 1);
	}

	/* Parser calls longjmp() on really fatal error (like out-of-memory). */
	ret = setjmp(parser_error_env);
	if (!ret)
		ret = yyparse();
	if (ret) {
		/* Either yyparse failed or longjmp() was called. */
		cgroup_err("failed to parse file %s\n", pathname);
		ret = ECGCONFIGPARSEFAIL;
		goto err;
	}

err:
	if (yyin)
		fclose(yyin);
	if (ret)
		cgroup_free_config();

	return ret;
}

int _cgroup_config_compare_groups(const void *p1, const void *p2)
{
	const struct cgroup *g1 = p1;
	const struct cgroup *g2 = p2;

	return strcmp(g1->name, g2->name);
}

static void cgroup_config_sort_groups(void)
{
	qsort(config_cgroup_table, cgroup_table_index, sizeof(struct cgroup),
	      _cgroup_config_compare_groups);
}

/*
 * The main function which does all the setup of the data structures and finally creates the
 * cgroups
 */
int cgroup_config_load_config(const char *pathname)
{
#ifdef WITH_SYSTEMD
	/* slice[FILENAME_MAX] + '/' + scope[FILENAME_MAX] */
	char tmp_systemd_default_cgroup[FILENAME_MAX * 2 + 1] = "\0";
#endif
	int namespace_enabled = 0;
	int mount_enabled = 0;
	int tmp_errno;
	int error;
	int ret;

	ret = cgroup_parse_config(pathname);
	if (ret != 0)
		return ret;

	namespace_enabled = (config_namespace_table[0].name[0] != '\0');
	mount_enabled = (config_mount_table[0].name[0] != '\0');

	/* The configuration should have namespace or mount, not both. */
	if (namespace_enabled && mount_enabled) {
		free(config_cgroup_table);
		return ECGMOUNTNAMESPACE;
	}

	error = cgroup_config_mount_fs();
	if (error)
		goto err_mnt;

	error = cgroup_init();
	if (error == ECGROUPNOTMOUNTED && cgroup_table_index == 0
	    && config_template_table_index == 0) {
		/* The config file seems to be empty. */
		error = 0;
		goto err_mnt;
	}
	if (error)
		goto err_mnt;

	/*
	 * The very first thing is to sort the namespace table. If we fail
	 * we unmount everything and get out.
	 */

	error = config_order_namespace_table();
	if (error)
		goto err_mnt;

	error = config_validate_namespaces();
	if (error)
		goto err_mnt;

#ifdef WITH_SYSTEMD
	error = config_create_slice_scope(tmp_systemd_default_cgroup);
	if (!error) {
		error = ECGROUPPARSEFAIL;
		goto err_mnt;
	}
#endif

	cgroup_config_apply_default();
	error = cgroup_config_create_groups();
	cgroup_dbg("creating all cgroups now, error=%d\n", error);
	if (error)
		goto err_grp;

#ifdef WITH_SYSTEMD
	/*
	 * Setting up systemd_default_cgroup, during creation
	 * of slice/scopes, clobbers the path returned by cg_build_path(),
	 * by appending the systemd_default_cgroup to it and
	 * its required only after all of the slices/scopes are created.
	 * The user might also set setdefault in more than one systemd
	 * delegate settings, in that case the last parsed one overwrites
	 * the systemd_default_cgroup.
	 */
	if (strlen(tmp_systemd_default_cgroup))
		snprintf(systemd_default_cgroup, sizeof(systemd_default_cgroup),
			 "%s", tmp_systemd_default_cgroup);
#endif

	cgroup_free_config();

	return 0;
err_grp:
	/*
	 * Save off the error that has already occurred.  The errno from
	 * cgroup_config_destroy_groups() is secondary to the original error that was reported,
	 * and thus can be discarded;
	 */
	tmp_errno = last_errno;
	cgroup_config_destroy_groups();
	last_errno = tmp_errno;
err_mnt:
	cgroup_config_unmount_controllers();
	cgroup_free_config();

	return error;
}

/* unmounts given mount, but only if it is empty */
static int cgroup_config_try_unmount(struct cg_mount_table_s *mount_info)
{
	struct cg_mount_point *mount = &(mount_info->mount);
	char *controller, *controller_list;
	struct cgroup_file_info info;
	void *handle = NULL;
	char *saveptr = NULL;
	int ret, lvl;

	/* parse the first controller name from list of controllers */
	controller_list = strdup(mount_info->name);
	if (!controller_list) {
		last_errno = errno;
		return ECGOTHER;
	}
	controller = strtok_r(controller_list, ",", &saveptr);
	if (!controller) {
		free(controller_list);
		return ECGINVAL;
	}

	/* check if the hierarchy is empty */
	ret = cgroup_walk_tree_begin(controller, "/", 0, &handle, &info, &lvl);
	free(controller_list);
	if (ret == ECGCONTROLLEREXISTS)
		return 0;
	if (ret)
		return ret;

	/* skip the first found directory, it's '/' */
	ret = cgroup_walk_tree_next(0, &handle, &info, lvl);
	/* find any other subdirectory */
	while (ret == 0) {
		if (info.type == CGROUP_FILE_TYPE_DIR)
			break;
		ret = cgroup_walk_tree_next(0, &handle, &info, lvl);
	}
	cgroup_walk_tree_end(&handle);
	if (ret == 0) {
		cgroup_dbg("won't unmount %s: hieararchy is not empty\n", mount_info->name);
		return 0; /* the hieararchy is not empty */
	}
	if (ret != ECGEOF)
		return ret;

	/*
	 * ret must be ECGEOF now = there is only root group in the
	 * hierarchy -> unmount all mount points.
	 */
	ret = 0;
	while (mount) {
		int err;

		cgroup_dbg("unmounting %s at %s\n", mount_info->name, mount->path);
		err = umount(mount->path);
		if (err && !ret) {
			ret = ECGOTHER;
			last_errno = errno;
		}
		mount = mount->next;
	}
	return ret;
}

int cgroup_config_unload_config(const char *pathname, int flags)
{
	int namespace_enabled = 0;
	int mount_enabled = 0;
	int ret, i, error;

	cgroup_dbg("%s: parsing %s\n", __func__, pathname);
	ret = cgroup_parse_config(pathname);
	if (ret)
		goto err;

	namespace_enabled = (config_namespace_table[0].name[0] != '\0');
	mount_enabled = (config_mount_table[0].name[0] != '\0');
	/* The configuration should have namespace or mount, not both. */
	if (namespace_enabled && mount_enabled) {
		free(config_cgroup_table);
		return ECGMOUNTNAMESPACE;
	}

	ret = config_order_namespace_table();
	if (ret)
		goto err;

	ret = config_validate_namespaces();
	if (ret)
		goto err;

	/* Delete the groups in reverse order, i.e. subgroups first, then parents. */
	cgroup_config_sort_groups();
	for (i = cgroup_table_index-1; i >= 0; i--) {
		struct cgroup *cgroup = &config_cgroup_table[i];

		cgroup_dbg("removing %s\n", pathname);
		error = cgroup_delete_cgroup_ext(cgroup, flags);
		if (error && !ret)
			/* store the error, but continue deleting the rest */
			ret = error;
	}

	/* Delete templates */
	for (i = 0; i < config_template_table_index; i++) {
		struct cgroup *cgroup = &config_template_table[i];

		cgroup_dbg("removing %s\n", pathname);
		error = cgroup_delete_cgroup_ext(cgroup, flags);
		if (error && !ret)
			ret = error; /* store the error, but continue deleting the rest */
	}
	config_template_table_index = 0;

	if (mount_enabled) {
		for (i = 0; i < config_table_index; i++) {
			struct cg_mount_table_s *m = &(config_mount_table[i]);

			cgroup_dbg("unmounting %s\n", m->name);
			error = cgroup_config_try_unmount(m);
			if (error && !ret)
				ret = error;
		}
	}
err:
	cgroup_free_config();

	return ret;
}

static int cgroup_config_unload_controller(const struct cgroup_mount_point *mount_info)
{
	struct cgroup_controller *cgc = NULL;
	struct cgroup *cgroup = NULL;
	enum cg_version_t version;
	char path[FILENAME_MAX];
	int ret, error;
	void *handle;

	cgroup = cgroup_new_cgroup(".");
	if (cgroup == NULL)
		return ECGFAIL;

	cgc = cgroup_add_controller(cgroup, mount_info->name);
	if (cgc == NULL) {
		ret = ECGFAIL;
		goto out_error;
	}

	ret = cgroup_delete_cgroup_ext(cgroup, CGFLAG_DELETE_RECURSIVE);
	if (ret != 0)
		goto out_error;

	ret = cgroup_get_controller_version(mount_info->name, &version);
	if (ret != 0)
		goto out_error;

	if (version == CGROUP_V2)
		/* do not unmount the controller */
		goto out_error;

	/* unmount everything */
	ret = cgroup_get_subsys_mount_point_begin(mount_info->name, &handle, path);
	while (ret == 0) {
		error = umount(path);
		if (error) {
			cgroup_warn("cannot unmount controller %s on %s: %s\n", mount_info->name,
				    path, strerror(errno));
			last_errno = errno;
			ret = ECGOTHER;
			goto out_error;
		}
		ret = cgroup_get_subsys_mount_point_next(&handle, path);
	}

	cgroup_get_subsys_mount_point_end(&handle);
	if (ret == ECGEOF)
		ret = 0;
out_error:
	if (cgroup)
		cgroup_free(&cgroup);

	return ret;
}

int cgroup_unload_cgroups(void)
{
	struct cgroup_mount_point info;
	void *ctrl_handle = NULL;
	char *curr_path = NULL;
	int error = 0;
	int ret = 0;

	error = cgroup_init();

	if (error) {
		ret = error;
		goto out_error;
	}

	error = cgroup_get_controller_begin(&ctrl_handle, &info);
	while (error == 0) {
		if (!curr_path || strcmp(info.path, curr_path) != 0) {
			if (curr_path)
				free(curr_path);

			curr_path = strdup(info.path);
			if (!curr_path)
				goto out_errno;

			error = cgroup_config_unload_controller(&info);
			if (error) {
				/* remember the error and continue unloading the rest. */
				cgroup_warn("cannot clear controller %s\n", info.name);
				ret = error;
				error = 0;
			}
		}
		error = cgroup_get_controller_next(&ctrl_handle, &info);
	}
	if (error == ECGEOF)
		error = 0;
	if (error)
		ret = error;
out_error:
	if (curr_path)
		free(curr_path);
	cgroup_get_controller_end(&ctrl_handle);

	return ret;

out_errno:
	last_errno = errno;
	cgroup_get_controller_end(&ctrl_handle);

	return ECGOTHER;
}

/**
 * Defines the default group. The parser puts content of 'default { }' to
 * topmost group in config_cgroup_table.
 * This function copies the permissions from it to our default cgroup.
 */
int cgroup_config_define_default(void)
{
	struct cgroup *config_cgroup = &config_cgroup_table[cgroup_table_index];

	init_cgroup_table(&default_group, 1);
	if (config_cgroup->control_dperm != NO_PERMS)
		default_group.control_dperm = config_cgroup->control_dperm;
	if (config_cgroup->control_fperm != NO_PERMS)
		default_group.control_fperm = config_cgroup->control_fperm;
	if (config_cgroup->control_gid != NO_UID_GID)
		default_group.control_gid = config_cgroup->control_gid;
	if (config_cgroup->control_uid != NO_UID_GID)
		default_group.control_uid = config_cgroup->control_uid;
	if (config_cgroup->task_fperm != NO_PERMS)
		default_group.task_fperm = config_cgroup->task_fperm;
	if (config_cgroup->tasks_gid != NO_UID_GID)
		default_group.tasks_gid = config_cgroup->tasks_gid;
	if (config_cgroup->tasks_uid != NO_UID_GID)
		default_group.tasks_uid = config_cgroup->tasks_uid;

	/*
	 * Reset all changes made by 'default { }' to the topmost group so
	 * it can be used by following 'group { }'.
	 */
	init_cgroup_table(config_cgroup, 1);

	return 0;
}

int cgroup_config_set_default(struct cgroup *new_default)
{
	if (!new_default)
		return ECGINVAL;

	init_cgroup_table(&default_group, 1);

	default_group.control_dperm = new_default->control_dperm;
	default_group.control_fperm = new_default->control_fperm;
	default_group.control_gid = new_default->control_gid;
	default_group.control_uid = new_default->control_uid;
	default_group.task_fperm = new_default->task_fperm;
	default_group.tasks_gid = new_default->tasks_gid;
	default_group.tasks_uid = new_default->tasks_uid;
	default_group_set = 1;

	return 0;
}

/**
 * Reloads the templates list, using the given configuration file.
 *	@return 0 on success, > 0 on failure
 */
int cgroup_reload_cached_templates(char *pathname)
{
	int ret = 0;
	int i;

	if (template_table) {
		/* template structures have to be free */
		for (i = 0; i < template_table_index; i++)
			cgroup_free_controllers(&template_table[i]);

		free(template_table);
		template_table = NULL;
	}
	template_table_index = 0;

	if ((config_template_table_index != 0) || (config_table_index != 0)) {
		/* config template structures have to be free as well*/
		cgroup_free_config();
	}

	/* reloading data to config template structures */
	cgroup_dbg("Reloading cached templates from %s.\n", pathname);
	ret = cgroup_parse_config(pathname);
	if (ret) {
		cgroup_dbg("Could not reload template cache, error was: %d\n", ret);
		return ret;
	}

	/* copy data to templates cache structures */
	template_table_index = config_template_table_index;
	template_table = calloc(template_table_index, sizeof(struct cgroup));
	if (template_table == NULL) {
		ret = ECGOTHER;
		return  ret;
	}

	for (i = 0; i < template_table_index; i++) {
		cgroup_copy_cgroup(&template_table[i], &config_template_table[i]);
		strcpy((template_table[i]).name, (config_template_table[i]).name);
		template_table[i].tasks_uid = config_template_table[i].tasks_uid;
		template_table[i].tasks_gid = config_template_table[i].tasks_gid;
		template_table[i].task_fperm = config_template_table[i].task_fperm;
		template_table[i].control_uid =	config_template_table[i].control_uid;
		template_table[i].control_gid =	config_template_table[i].control_gid;
		template_table[i].control_fperm = config_template_table[i].control_fperm;
		template_table[i].control_dperm = config_template_table[i].control_dperm;
	}

	return ret;
}

/**
 * Initializes the templates cache.
 *	@return 0 on success, > 0 on error
 */
int cgroup_init_templates_cache(char *pathname)
{
	int ret = 0;
	int i;

	if (template_table) {
		/* template structures have to be free */
		for (i = 0; i < template_table_index; i++)
			cgroup_free_controllers(&template_table[i]);

		free(template_table);
		template_table = NULL;
	}
	template_table_index = 0;

	if ((config_template_table_index != 0) || (config_table_index != 0)) {
		/* config structures have to be clean */
		cgroup_free_config();
	}

	cgroup_dbg("Loading cached templates from %s.\n", pathname);
	/* Attempt to read the configuration file and cache the rules. */
	ret = cgroup_parse_config(pathname);
	if (ret) {
		cgroup_dbg("Could not initialize rule cache, error was: %d\n", ret);
		return ret;
	}

	/* copy template data to templates cache structures */
	template_table_index = config_template_table_index;
	template_table = calloc(template_table_index, sizeof(struct cgroup));
	if (template_table == NULL) {
		ret = ECGOTHER;
		return ret;
	}

	for (i = 0; i < template_table_index; i++) {
		cgroup_copy_cgroup(&template_table[i], &config_template_table[i]);
		strcpy((template_table[i]).name, (config_template_table[i]).name);
		template_table[i].tasks_uid = config_template_table[i].tasks_uid;
		template_table[i].tasks_gid = config_template_table[i].tasks_gid;
		template_table[i].task_fperm = config_template_table[i].task_fperm;
		template_table[i].control_uid =	config_template_table[i].control_uid;
		template_table[i].control_gid =	config_template_table[i].control_gid;
		template_table[i].control_fperm = config_template_table[i].control_fperm;
		template_table[i].control_dperm = config_template_table[i].control_dperm;
	}

	return ret;
}

/**
 * Setting source files of templates. This function has to be called before
 * any call of cgroup_load_templates_cache_from_files.
 * @param tmpl_files
 */
void cgroup_templates_cache_set_source_files(struct cgroup_string_list *tmpl_files)
{
	template_files = tmpl_files;
}

/**
 * Appending cgroup templates parsed by parser to template_table
 * @param offset number of templates already in the table
 */
int cgroup_add_cgroup_templates(int offset)
{
	int i, ti, ret;

	for (i = 0; i < config_template_table_index; i++) {
		ti = i + offset;
		ret = cgroup_copy_cgroup(&template_table[ti], &config_template_table[i]);
		if (ret)
			return ret;

		strcpy((template_table[ti]).name, (config_template_table[i]).name);
		template_table[ti].tasks_uid = config_template_table[i].tasks_uid;
		template_table[ti].tasks_gid = config_template_table[i].tasks_gid;
		template_table[ti].task_fperm =	config_template_table[i].task_fperm;
		template_table[ti].control_uid = config_template_table[i].control_uid;
		template_table[ti].control_gid = config_template_table[i].control_gid;
		template_table[ti].control_fperm = config_template_table[i].control_fperm;
		template_table[ti].control_dperm = config_template_table[i].control_dperm;
	}

	return 0;
}

/**
 * Expand template table based on new number of parsed templates,
 * i.e. on value of config_template_table_index. Change value of
 * template_table_index.
 * @return 0 on success, < 0 on error
 */
int cgroup_expand_template_table(void)
{
	int i;

	template_table = realloc(template_table,
				 (template_table_index + config_template_table_index)
				 *sizeof(struct cgroup));
	if (template_table == NULL)
		return -ECGOTHER;

	for (i = 0; i < config_template_table_index; i++)
		template_table[i + template_table_index].index = 0;

	template_table_index += config_template_table_index;

	return 0;
}

/**
 * Load the templates cache from files. Before calling this function,
 * cgroup_templates_cache_set_source_files has to be called first.
 * @param file_index index of file which was unable to be parsed
 * @return 0 on success, > 0 on error
 */
int cgroup_load_templates_cache_from_files(int *file_index)
{
	int template_table_last_index;
	char *pathname;
	int i, j;
	int ret;

	if (!template_files) {
		/* source files has not been set */
		cgroup_dbg("Template source files have not been set. Using only %s\n",
			   CGCONFIG_CONF_FILE);

		if (template_table_index == 0)
			/* the rules cache is empty */
			return cgroup_init_templates_cache(CGCONFIG_CONF_FILE);
		else
			/* cache is not empty */
			return cgroup_reload_cached_templates(CGCONFIG_CONF_FILE);
	}

	if (template_table) {
		/* template structures have to be free */
		for (i = 0; i < template_table_index; i++)
			cgroup_free_controllers(&template_table[i]);

		free(template_table);
		template_table = NULL;
	}
	template_table_index = 0;

	if ((config_template_table_index != 0) || (config_table_index != 0)) {
		/* config structures have to be clean before parsing */
		cgroup_free_config();
	}

	for (j = 0; j < template_files->count; j++) {
		pathname = template_files->items[j];

		cgroup_dbg("Parsing templates from %s.\n", pathname);
		/* Attempt to read the configuration file and cache the rules. */
		ret = cgroup_parse_config(pathname);
		if (ret) {
			cgroup_dbg("Could not initialize rule cache, error was: %d\n", ret);
			*file_index = j;
			return ret;
		}

		if (config_template_table_index > 0) {
			template_table_last_index = template_table_index;
			ret = cgroup_expand_template_table();
			if (ret) {
				cgroup_dbg("Could not expand template table, error was: %d\n",
					    -ret);
				*file_index = j;
				return -ret;
			}

			/* copy template data to templates cache structures */
			cgroup_dbg("Copying templates to template table from %s.\n", pathname);
			ret = cgroup_add_cgroup_templates(template_table_last_index);
			if (ret) {
				cgroup_dbg("Unable to copy cgroup\n");
				*file_index = j;
				return ret;
			}
			cgroup_dbg("Templates to template table copied\n");
		}
	}

	return 0;
}

/*
 * Create a given cgroup, based on template configuration if it is present
 * if the template is not present cgroup is creted using cgroup_create_cgroup
 */
int cgroup_config_create_template_group(struct cgroup *cgroup, char *template_name, int flags)
{
	struct cgroup *aux_cgroup = NULL;
	struct cgroup_controller *cgc;
	char buffer[FILENAME_MAX];
	struct cgroup *t_cgroup;
	int i, j, k;
	int ret = 0;
	int found;

	/*
	 * If the user did not ask for cached rules, we must parse the
	 * configuration file and prepare template structures now.
	 * We use CGCONFIG_CONF_FILE by default
	 */
	if (!(flags & CGFLAG_USE_TEMPLATE_CACHE)) {
		int fileindex;

		/* the rules cache is empty */
		ret = cgroup_load_templates_cache_from_files(
			&fileindex);
		if (ret != 0) {
			if (fileindex < 0) {
				cgroup_dbg("Template source files have not been set\n");
			} else {
				cgroup_dbg("Failed to load template rules from %s. ",
					   template_files->items[fileindex]);
			}
		}

		if (ret != 0) {
			cgroup_dbg("Failed initialize templates cache.\n");
			return ret;
		}
	}

	for (i = 0; cgroup->controller[i] != NULL; i++) {
		/*
		 * for each controller we have to add to cgroup structure
		 * either template cgroup or empty controller.
		 */

		found = 0;
		/* look for relevant template - test name x controller pair */
		for (j = 0; j < template_table_index; j++) {

			t_cgroup = &template_table[j];
			if (strcmp(t_cgroup->name, template_name) != 0) {
				/* template name does not match skip template */
				continue;
			}

			/* template name match */
			for (k = 0; t_cgroup->controller[k] != NULL; k++) {
				if (strcmp((cgroup->controller[i])->name,
					(t_cgroup->controller[k])->name) != 0) {
					/* controller name does not match */
					continue;
				}

				/*
				 * name and controller match template found
				 * variables substituted in template
				 */
				strncpy(buffer, t_cgroup->name, FILENAME_MAX-1);
				buffer[sizeof(buffer) - 1] = '\0';
				strncpy(t_cgroup->name, cgroup->name, FILENAME_MAX-1);
				t_cgroup->name[sizeof(t_cgroup->name) - 1] = '\0';

				ret = cgroup_create_cgroup(t_cgroup, flags);

				strncpy(t_cgroup->name, buffer,	FILENAME_MAX-1);
				t_cgroup->name[sizeof(t_cgroup->name) - 1] = '\0';
				if (ret) {
					cgroup_dbg("creating group %s, error %d\n", cgroup->name,
						   ret);
					goto end;
				} else {
					/* go to new controller */
					j = template_table_index;
					found = 1;
					continue;
				}

			}
		}

		if (found == 1)
			continue;

		/*
		 * no template is present for given name x controller pair
		 * add controller to result cgroup.
		 */
		aux_cgroup = cgroup_new_cgroup(cgroup->name);
		if (!aux_cgroup) {
			ret = ECGINVAL;
			fprintf(stderr, "cgroup %s can't be created\n",	cgroup->name);
			goto end;
		}
		cgc = cgroup_add_controller(aux_cgroup, (cgroup->controller[i])->name);
		if (cgc == NULL) {
			ret = ECGINVAL;
			fprintf(stderr, "cgroup %s can't be created\n", cgroup->name);
			goto end;
		}
		ret = cgroup_create_cgroup(aux_cgroup, flags);
		if (ret) {
			ret = ECGINVAL;
			fprintf(stderr, "cgroup %s can't be created\n",	cgroup->name);
			goto end;
		}
	}

end:
	cgroup_free(&aux_cgroup);
	return ret;
}

#ifdef WITH_SYSTEMD
int cgroup_add_systemd_opts(const char * const config, const char * const value)
{
	struct cgroup_systemd_opts *curr = cgroup_systemd_opts_tail;
	int len;

	if (strcmp(config, "slice") == 0) {
		snprintf(curr->slice_name, FILENAME_MAX, "%s", value);

		len = strlen(curr->slice_name) - 6;
		if (strcmp(curr->slice_name + len, ".slice"))
			goto err;

	} else if (strcmp(config, "scope") == 0) {
		snprintf(curr->scope_name, FILENAME_MAX, "%s", value);

		len = strlen(curr->scope_name) - 6;
		if (strcmp(curr->scope_name + len, ".scope"))
			goto err;

	} else if (strcmp(config, "setdefault") == 0 && strcasecmp(value, "yes") == 0) {
		curr->setdefault = 1;
	} else if (strcmp(config, "pid") == 0) {
		/*
		 * If the atoi() fails, the pid value is zero and also
		 * avoid allowing init task (systemd).
		 */
		curr->pid = atoi(value);
		if (curr->pid <= 1)
			goto err;
	} else
		goto err;

	return 1;
err:
	cgroup_err("Invalid systemd configuration %s value %s\n", config, value);
	cgroup_cleanup_systemd_opts();
	return 0;
}

int cgroup_alloc_systemd_opts(const char * const config, const char * const value)
{
	struct cgroup_systemd_opts *new_cgrp_systemd_opts;

	/*
	 * check for the allowed systemd configurations. We don't
	 * check for values, they will be checked by systemd anyway.
	 */
	if (strcmp(config, "slice") != 0 &&
	    strcmp(config, "scope") != 0 &&
	    strcmp(config, "setdefault") != 0 &&
	    strcmp(config, "pid") != 0) {
		cgroup_err("Invalid systemd configuration %s\n", config);
		goto err;
	}

	new_cgrp_systemd_opts = calloc(1, sizeof(struct cgroup_systemd_opts));
	if (!new_cgrp_systemd_opts) {
		cgroup_err("Failed to allocate memory for cgroup_systemd_opts\n");
		goto err;
	}

	if (!cgroup_systemd_opts_head)
		cgroup_systemd_opts_tail = cgroup_systemd_opts_head = new_cgrp_systemd_opts;
	else {
		cgroup_systemd_opts_tail->next = new_cgrp_systemd_opts;
		cgroup_systemd_opts_tail = new_cgrp_systemd_opts;
	}

	return cgroup_add_systemd_opts(config, value);
err:
	cgroup_cleanup_systemd_opts();
	return 0;
}

void cgroup_cleanup_systemd_opts(void)
{
	struct cgroup_systemd_opts *curr, *next;

	for (curr = cgroup_systemd_opts_head; curr; curr = next) {
		next = curr->next;
		free(curr);
	}

	cgroup_systemd_opts_head = cgroup_systemd_opts_tail = NULL;
}

/*
 * Helper function to remove the systemd_default_cgroup_file.
 * systemd_default_cgroup_lock is expected to be held by the
 * caller.
 */
static int remove_systemd_default_cgroup_file(void)
{
	int ret;

	ret = unlink(systemd_default_cgroup_file);
	if (ret < 0 && errno != ENOENT) {
		cgroup_err("Failed to remove %s\n", systemd_default_cgroup_file);
		return 0;
	}

	return 1;
}
/*
 * Helper function to create systemd_default_cgroup_file and write systemd
 * default cgroup slice/scope into it.  This file will be read by
 * cgroup_set_default_systemd_cgroup() for setting
 * systemd_default_cgroup used to form the cgroup path.
 */
int cgroup_write_systemd_default_cgroup(const char * const slice,
					const char * const scope)
{
	FILE *systemd_def_cgrp_f;
	int ret, len;

	pthread_rwlock_wrlock(&systemd_default_cgroup_lock);

	ret = mkdir(systemd_def_cgrp_file_dir, 0755);
	if (ret != 0 && errno != EEXIST) {
		cgroup_err("Failed to create directory %s\n", systemd_def_cgrp_file_dir);
		ret = 0;
		goto out;
	}

	systemd_def_cgrp_f = fopen(systemd_default_cgroup_file, "w");
	if (!systemd_def_cgrp_f) {
		cgroup_err("Failed to create file %s\n", systemd_default_cgroup_file);
		ret = 0;
		goto out;
	}

	len = strlen(slice) + strlen(scope) + 1;

	ret = fprintf(systemd_def_cgrp_f, "%s/%s", slice, scope);
	fclose(systemd_def_cgrp_f);
	if  (ret != len) {
		cgroup_err("Incomplete systemd default cgroup written to %s\n",
			   systemd_default_cgroup_file);
		ret = remove_systemd_default_cgroup_file();
		/* Ignore the return value, we are already in error path */
		ret = 0;
		goto out;
	}

	ret = 1;
out:
	pthread_rwlock_unlock(&systemd_default_cgroup_lock);
	return ret;
}

/**
 * Create the systemd slice and scope. The slice/scope are parsed and available in
 * the cgroup_systemd_opts_head list. This function skips the slice and scope creation
 * if previously created.
 *
 * Returns 1 on success and 0 on failure.
 */
static int config_create_slice_scope(char * const tmp_systemd_default_cgroup)
{
	struct cgroup_systemd_opts *curr, *def = NULL;
	struct cgroup_systemd_scope_opts scope_opts;
	int ret = 0;

	if (!tmp_systemd_default_cgroup)
		return 0;

	if (cgroup_set_default_scope_opts(&scope_opts))
		return 0;

	pthread_rwlock_wrlock(&systemd_default_cgroup_lock);
	ret = remove_systemd_default_cgroup_file();
	pthread_rwlock_unlock(&systemd_default_cgroup_lock);
	if (!ret)
		return 0;

	for (curr = cgroup_systemd_opts_head; curr; curr = curr->next) {

		if (!strlen(curr->slice_name)) {
			cgroup_err("Invalid systemd setting, missing slice name.\n");
			goto err;
		}

		if (!strlen(curr->scope_name)) {
			cgroup_err("Invalid systemd setting, missing scope name.\n");
			goto err;
		}

		/* incase of multiple setdefault configurations, set it to latest scope */
		if (curr->setdefault)
			def = curr;

		if (curr->pid)
			scope_opts.pid = curr->pid;

		ret = cgroup_create_scope(curr->scope_name, curr->slice_name, &scope_opts);
		if (ret)
			goto err;

		cgroup_dbg("Created systemd slice %s scope %s default %d pid %d\n",
			   curr->slice_name, curr->scope_name, curr->setdefault, curr->pid);
	}

	if (def) {
		if (!cgroup_write_systemd_default_cgroup(def->slice_name, def->scope_name))
			goto err;

		snprintf(tmp_systemd_default_cgroup, sizeof(systemd_default_cgroup),
			 "%s/%s", def->slice_name, def->scope_name);

		cgroup_dbg("Setting/Writing systemd default cgroup %s to file %s\n",
			   tmp_systemd_default_cgroup, systemd_default_cgroup_file);

	}

	return 1;
err:
	return 0;
}

/*
 * This function builds the canonical path for the systemd default cgroup
 * based on the cgroup setup mode and if the cgroup doesn't exist removes
 * the systemd default cgroup file. This function expects the caller to be
 * holding the systemd_default_cgroup_lock.
 */
static bool systemd_default_cgroup_exists(void)
{
	char path[FILENAME_MAX] = "\0";
	char cgrp_procs_path[FILENAME_MAX + 13]; /* 13 = strlen(/cgroup.procs) */
	bool exists = false;

	switch (cgroup_setup_mode()) {
	case CGROUP_MODE_HYBRID:
		/*
		 * check for empty cgroup v2, the most common usage in
		 * the hybrid case.
		 */
		if (cg_build_path("", path, NULL))
			break;
	case CGROUP_MODE_UNIFIED:
		/* fallthrough */
	case CGROUP_MODE_LEGACY:
		cg_build_path("", path, "cpu");
		/* fallthrough */
	case CGROUP_MODE_UNK:
		break;
	}

	if (!strlen(path)) {
		cgroup_dbg("Unable to form canonical path for %s", systemd_default_cgroup);
		goto err;
	}

	snprintf(cgrp_procs_path, sizeof(cgrp_procs_path), "%s/cgroup.procs", path);
	if (access(cgrp_procs_path, F_OK)) {
		/*
		 * systemd has already removed the scope cgroup, hence delete
		 * the file too.
		 */
		cgroup_dbg("%s doesn't exists, deleting %s", path, systemd_default_cgroup_file);
		unlink(systemd_default_cgroup_file);
		goto err;
	}

	exists = true;
err:
	return exists;
}

int cgroup_set_default_systemd_cgroup(void)
{
	FILE *systemd_def_cgrp_f;
	size_t len;

	pthread_rwlock_wrlock(&systemd_default_cgroup_lock);

	systemd_def_cgrp_f = fopen(systemd_default_cgroup_file, "r");
	if (!systemd_def_cgrp_f) {
		cgroup_dbg("Unable to read %s ", systemd_default_cgroup_file);
		goto err;
	}

	len = fread(systemd_default_cgroup, sizeof(char), FILENAME_MAX, systemd_def_cgrp_f);
	fclose(systemd_def_cgrp_f);
	/* at the minimum it should be <n>.slice/<n>.scope == 15 */
	if (len < 15) {
		cgroup_dbg("Malformed systemd default cgroup %s", systemd_default_cgroup);
		goto err;
	}

	if (systemd_default_cgroup_exists()) {
		pthread_rwlock_unlock(&systemd_default_cgroup_lock);
		return 1;
	}
err:
	pthread_rwlock_unlock(&systemd_default_cgroup_lock);
	cgroup_dbg(", continuing without systemd default cgroup.\n", systemd_default_cgroup);
	systemd_default_cgroup[0] = '\0';

	return 0;
}
#else
int cgroup_add_systemd_opts(const char * const config, const char * const value)
{
	return 1;
}

int cgroup_alloc_systemd_opts(const char * const config, const char * const value)
{
	return 1;
}

void cgroup_cleanup_systemd_opts(void) { }

int cgroup_set_default_systemd_cgroup(void)
{
	return 0;
}
#endif
