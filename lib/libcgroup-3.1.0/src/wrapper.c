// SPDX-License-Identifier: LGPL-2.1-only
/**
 * Copyright IBM Corporation. 2008
 *
 * Author:	Dhaval Giani <dhaval@linux.vnet.ibm.com>
 *
 * Code initiated and designed by Dhaval Giani. All faults are most likely
 * his mistake.
 */

#define _GNU_SOURCE

#include <libcgroup.h>
#include <libcgroup-internal.h>

#include <inttypes.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <stdio.h>
#include <errno.h>

static void init_cgroup(struct cgroup *cgroup)
{
	cgroup->task_fperm = NO_PERMS;
	cgroup->control_fperm = NO_PERMS;
	cgroup->control_dperm = NO_PERMS;

	cgroup->control_gid = NO_UID_GID;
	cgroup->control_uid = NO_UID_GID;
	cgroup->tasks_gid = NO_UID_GID;
	cgroup->tasks_uid = NO_UID_GID;
}

void init_cgroup_table(struct cgroup *cgroups, size_t count)
{
	size_t i;

	for (i = 0; i < count; ++i)
		init_cgroup(&cgroups[i]);
}

struct cgroup *cgroup_new_cgroup(const char *name)
{
	struct cgroup *cgroup;

	if (!name)
		return NULL;

	cgroup = calloc(1, sizeof(struct cgroup));
	if (!cgroup)
		return NULL;

	init_cgroup(cgroup);
	strncpy(cgroup->name, name, FILENAME_MAX - 1);
	cgroup->name[FILENAME_MAX - 1] = '\0';

	return cgroup;
}

struct cgroup_controller *cgroup_add_controller(struct cgroup *cgroup, const char *name)
{
	struct cgroup_controller *controller;
	int i, ret;

	if (!cgroup || !name)
		return NULL;

	/* Still not sure how to handle the failure here. */
	if (cgroup->index >= CG_CONTROLLER_MAX)
		return NULL;

	/* Still not sure how to handle the failure here. */
	for (i = 0; i < cgroup->index; i++) {
		if (strncmp(name, cgroup->controller[i]->name, CONTROL_NAMELEN_MAX) == 0)
			return NULL;
	}

	controller = calloc(1, sizeof(struct cgroup_controller));
	if (!controller)
		return NULL;

	strncpy(controller->name, name, CONTROL_NAMELEN_MAX - 1);
	controller->name[CONTROL_NAMELEN_MAX - 1] = '\0';

	controller->cgroup = cgroup;
	controller->index = 0;

	if (strcmp(controller->name, CGROUP_FILE_PREFIX) == 0) {
		/*
		 * Operating on the "cgroup" controller is only allowed
		 * on cgroup v2 systems
		 */
		controller->version = CGROUP_V2;
	} else {
		ret = cgroup_get_controller_version(controller->name, &controller->version);
		if (ret) {
			cgroup_dbg("failed to get cgroup version for controller %s\n",
				   controller->name);
			free(controller);
			return NULL;
		}
	}

	cgroup->controller[cgroup->index] = controller;
	cgroup->index++;

	return controller;
}

int cgroup_add_all_controllers(struct cgroup *cgroup)
{
	struct cgroup_controller *cgc;
	struct controller_data info;
	enum cg_setup_mode_t mode;
	void *handle;
	int ret = 0;

	if (!cgroup)
		return ECGINVAL;

	mode = cgroup_setup_mode();

	/*
	 * Per kernel documentation, cgroup-v2.rst, /proc/cgroups is "meaningless" for cgroup v2.
	 * Use the cgroup's cgroup.controllers file instead
	 */
	if (mode == CGROUP_MODE_UNIFIED) {
		char *ret_c, *controller, *stok_buff = NULL, line[CGV2_CONTROLLERS_LL_MAX];
		/*
		 * cg_cgroup_v2_mount_path (FILENAME_MAX) + cgroup->name (FILENAME_MAX) +
		 * strlen("cgroup.controllers") (18) + 2 forward slashes + 1 NULL terminator
		 */
		char cgroup_controllers_path[FILENAME_MAX * 2 + 18 + 2 + 1];
		FILE *fp;

		pthread_rwlock_rdlock(&cg_mount_table_lock);
		if (strlen(cg_cgroup_v2_mount_path) == 0) {
			pthread_rwlock_unlock(&cg_mount_table_lock);
			ret = ECGOTHER;
			goto out;
		}

		snprintf(cgroup_controllers_path, sizeof(cgroup_controllers_path), "%s/%s/%s",
			 cg_cgroup_v2_mount_path, cgroup->name, CGV2_CONTROLLERS_FILE);
		pthread_rwlock_unlock(&cg_mount_table_lock);

		fp = fopen(cgroup_controllers_path, "re");
		if (!fp) {
			ret = ECGOTHER;
			goto out;
		}

		ret_c = fgets(line, CGV2_CONTROLLERS_LL_MAX, fp);
		fclose(fp);
		if (ret_c == NULL) {
			/* no controllers are enabled */
			goto out;
		}

		/* Remove the trailing newline */
		ret_c[strlen(ret_c) - 1] = '\0';

		/*
		 * cgroup.controllers returns a list of available controllers in
		 * the following format:
		 *	cpuset cpu io memory pids rdma
		 */
		controller = strtok_r(ret_c, " ", &stok_buff);
		do {
			cgc = cgroup_add_controller(cgroup, controller);
			if (!cgc) {
				ret = ECGINVAL;
				fprintf(stderr, "controller %s can't be added\n", controller);
				goto end;
			}
		} while ((controller = strtok_r(NULL, " ", &stok_buff)));
	} else {
		/* go through the controller list */
		ret = cgroup_get_all_controller_begin(&handle, &info);
		if ((ret != 0) && (ret != ECGEOF)) {
			fprintf(stderr, "cannot read controller data: %s\n", cgroup_strerror(ret));
			return ret;
		}

		while (ret == 0) {
			if (info.hierarchy == 0) {
				/*
				 * the controller is not attached to any
				 * hierarchy skip it.
				 */
				goto next;
			}

			/* add mounted controller to cgroup structure */
			cgc = cgroup_add_controller(cgroup, info.name);
			if (!cgc) {
				ret = ECGINVAL;
				fprintf(stderr, "controller %s can't be added\n", info.name);
				goto end;
			}

next:
			ret = cgroup_get_all_controller_next(&handle, &info);
			if (ret && ret != ECGEOF)
				goto end;
		}

end:
		cgroup_get_all_controller_end(&handle);
		if (ret == ECGEOF)
			ret = 0;
		if (ret)
			fprintf(stderr,	"cgroup_get_controller_begin/next failed (%s)\n",
				cgroup_strerror(ret));
	}

out:
	return ret;
}

static void cgroup_free_value(struct control_value *value)
{
	if (value->multiline_value)
		free(value->multiline_value);
	if (value->prev_name)
		free(value->prev_name);

	free(value);
}

void cgroup_free_controller(struct cgroup_controller *ctrl)
{
	int i;

	for (i = 0; i < ctrl->index; i++)
		cgroup_free_value(ctrl->values[i]);
	ctrl->index = 0;

	free(ctrl);
}

void cgroup_free_controllers(struct cgroup *cgroup)
{
	int i;

	if (!cgroup)
		return;

	for (i = 0; i < cgroup->index; i++)
		cgroup_free_controller(cgroup->controller[i]);

	cgroup->index = 0;
}

void cgroup_free(struct cgroup **cgroup)
{
	struct cgroup *cg = *cgroup;

	/* Passing NULL pointers is OK. We just return. */
	if (!cg)
		return;

	cgroup_free_controllers(cg);
	free(cg);
	*cgroup = NULL;
}

int cgroup_add_value_string(struct cgroup_controller *controller, const char *name,
			    const char *value)
{
	int i;
	struct control_value *cntl_value;

	if (!controller || !name)
		return ECGINVAL;

	if (controller->index >= CG_NV_MAX)
		return ECGMAXVALUESEXCEEDED;

	for (i = 0; i < controller->index && i < CG_NV_MAX; i++) {
		if (!strcmp(controller->values[i]->name, name))
			return ECGVALUEEXISTS;
	}

	cntl_value = calloc(1, sizeof(struct control_value));
	if (!cntl_value)
		return ECGCONTROLLERCREATEFAILED;

	strncpy(cntl_value->name, name, sizeof(cntl_value->name));
	cntl_value->name[sizeof(cntl_value->name)-1] = '\0';

	if (value) {
		if (strlen(value) >= sizeof(cntl_value->value)) {
			fprintf(stderr, "value exceeds the maximum of %ld characters\n",
				sizeof(cntl_value->value) - 1);
			free(cntl_value);
			return ECGCONFIGPARSEFAIL;
		}

		strncpy(cntl_value->value, value, sizeof(cntl_value->value));
		cntl_value->value[sizeof(cntl_value->value)-1] = '\0';
		cntl_value->dirty = true;
	}

	controller->values[controller->index] = cntl_value;
	controller->index++;

	return 0;
}

int cgroup_add_value_int64(struct cgroup_controller *controller, const char *name, int64_t value)
{
	char *val;
	int ret;

	ret = asprintf(&val, "%"PRId64, value);
	if (ret < 0) {
		last_errno = errno;
		return ECGOTHER;
	}

	ret = cgroup_add_value_string(controller, name, val);
	free(val);

	return ret;
}

int cgroup_add_value_uint64(struct cgroup_controller *controller, const char *name,
			    u_int64_t value)
{
	char *val;
	int ret;

	ret = asprintf(&val, "%" PRIu64, value);
	if (ret < 0) {
		last_errno = errno;
		return ECGOTHER;
	}

	ret = cgroup_add_value_string(controller, name, val);
	free(val);

	return ret;
}

int cgroup_add_value_bool(struct cgroup_controller *controller, const char *name, bool value)
{
	char *val;
	int ret;

	if (value)
		val = strdup("1");
	else
		val = strdup("0");
	if (!val) {
		last_errno = errno;
		return ECGOTHER;
	}

	ret = cgroup_add_value_string(controller, name, val);
	free(val);

	return ret;
}

int cgroup_remove_value(struct cgroup_controller * const controller, const char * const name)
{
	int i;

	for (i = 0; i < controller->index; i++) {
		if (strcmp(controller->values[i]->name, name) == 0) {
			cgroup_free_value(controller->values[i]);

			if (i == (controller->index - 1)) {
				/* This is the last entry in the table. There's nothing to move */
				controller->index--;
			} else {
				memmove(&controller->values[i],	&controller->values[i + 1],
				sizeof(struct control_value *) * (controller->index - i - 1));
				controller->index--;
			}
			return 0;
		}
	}

	return ECGROUPNOTEXIST;
}

int cgroup_compare_controllers(struct cgroup_controller *cgca, struct cgroup_controller *cgcb)
{
	int i;

	if (!cgca || !cgcb)
		return ECGINVAL;

	if (strcmp(cgca->name, cgcb->name))
		return ECGCONTROLLERNOTEQUAL;

	if (cgca->index != cgcb->index)
		return ECGCONTROLLERNOTEQUAL;

	for (i = 0; i < cgca->index; i++) {
		struct control_value *cva = cgca->values[i];
		struct control_value *cvb = cgcb->values[i];

		if (strcmp(cva->name, cvb->name))
			return ECGCONTROLLERNOTEQUAL;

		if (strcmp(cva->value, cvb->value))
			return ECGCONTROLLERNOTEQUAL;
	}

	return 0;
}

int cgroup_compare_cgroup(struct cgroup *cgroup_a, struct cgroup *cgroup_b)
{
	int i, j;

	if (!cgroup_a || !cgroup_b)
		return ECGINVAL;

	if (strcmp(cgroup_a->name, cgroup_b->name))
		return ECGROUPNOTEQUAL;

	if (cgroup_a->tasks_uid != cgroup_b->tasks_uid)
		return ECGROUPNOTEQUAL;

	if (cgroup_a->tasks_gid != cgroup_b->tasks_gid)
		return ECGROUPNOTEQUAL;

	if (cgroup_a->control_uid != cgroup_b->control_uid)
		return ECGROUPNOTEQUAL;

	if (cgroup_a->control_gid != cgroup_b->control_gid)
		return ECGROUPNOTEQUAL;

	if (cgroup_a->index != cgroup_b->index)
		return ECGROUPNOTEQUAL;

	for (i = 0; i < cgroup_a->index; i++) {
		struct cgroup_controller *cgca = cgroup_a->controller[i];
		bool found_match = false;

		/*
		 * Don't penalize the user if the controllers are in different order
		 * from cgroup_a to cgroup_b
		 */
		for (j = 0; j < cgroup_b->index; j++) {
			struct cgroup_controller *cgcb = cgroup_b->controller[j];

			if (cgroup_compare_controllers(cgca, cgcb) == 0) {
				found_match = true;
				break;
			}
		}

		if (!found_match)
			return ECGCONTROLLERNOTEQUAL;
	}

	return 0;
}

int cgroup_set_uid_gid(struct cgroup *cgroup, uid_t tasks_uid, gid_t tasks_gid, uid_t control_uid,
		       gid_t control_gid)
{
	if (!cgroup)
		return ECGINVAL;

	cgroup->tasks_uid = tasks_uid;
	cgroup->tasks_gid = tasks_gid;
	cgroup->control_uid = control_uid;
	cgroup->control_gid = control_gid;

	return 0;
}

int cgroup_get_uid_gid(struct cgroup *cgroup, uid_t *tasks_uid, gid_t *tasks_gid,
		       uid_t *control_uid, gid_t *control_gid)
{
	if (!cgroup || !tasks_uid || !tasks_gid || !control_uid || !control_gid)
		return ECGINVAL;

	*tasks_uid = cgroup->tasks_uid;
	*tasks_gid = cgroup->tasks_gid;
	*control_uid = cgroup->control_uid;
	*control_gid = cgroup->control_gid;

	return 0;
}

struct cgroup_controller *cgroup_get_controller(struct cgroup *cgroup, const char *name)
{
	int i;
	struct cgroup_controller *cgc;

	if (!cgroup)
		return NULL;

	for (i = 0; i < cgroup->index; i++) {
		cgc = cgroup->controller[i];

		if (!strcmp(cgc->name, name))
			return cgc;
	}

	return NULL;
}

int cgroup_get_value_string(struct cgroup_controller *controller, const char *name, char **value)
{
	int i;

	if (!controller || !name || !value)
		return ECGINVAL;

	for (i = 0; i < controller->index; i++) {
		struct control_value *val = controller->values[i];

		if (!strcmp(val->name, name)) {
			*value = strdup(val->value);

			if (!*value)
				return ECGOTHER;

			return 0;
		}
	}

	return ECGROUPVALUENOTEXIST;

}

int cgroup_set_value_string(struct cgroup_controller *controller, const char *name,
			    const char *value)
{
	int i;

	if (!controller || !name || !value)
		return ECGINVAL;

	for (i = 0; i < controller->index; i++) {
		struct control_value *val = controller->values[i];

		if (!strcmp(val->name, name)) {
			strncpy(val->value, value, CG_CONTROL_VALUE_MAX);
			val->value[sizeof(val->value)-1] = '\0';
			val->dirty = true;
			return 0;
		}
	}

	return cgroup_add_value_string(controller, name, value);
}

int cgroup_get_value_int64(struct cgroup_controller *controller, const char *name, int64_t *value)
{
	int i;

	if (!controller || !name || !value)
		return ECGINVAL;

	for (i = 0; i < controller->index; i++) {
		struct control_value *val = controller->values[i];

		if (!strcmp(val->name, name)) {
			if (sscanf(val->value, "%" SCNd64, value) != 1)
				return ECGINVAL;

			return 0;
		}
	}

	return ECGROUPVALUENOTEXIST;
}

int cgroup_set_value_int64(struct cgroup_controller *controller, const char *name, int64_t value)
{
	int ret;
	int i;

	if (!controller || !name)
		return ECGINVAL;

	for (i = 0; i < controller->index; i++) {
		struct control_value *val = controller->values[i];

		if (!strcmp(val->name, name)) {
			ret = snprintf(val->value, sizeof(val->value), "%" PRId64, value);
			if (ret >= sizeof(val->value))
				return ECGINVAL;

			val->dirty = true;
			return 0;
		}
	}

	return cgroup_add_value_int64(controller, name, value);
}

int cgroup_get_value_uint64(struct cgroup_controller *controller, const char *name,
			    u_int64_t *value)
{
	int i;

	if (!controller || !name || !value)
		return ECGINVAL;

	for (i = 0; i < controller->index; i++) {
		struct control_value *val = controller->values[i];

		if (!strcmp(val->name, name)) {
			if (sscanf(val->value, "%" SCNu64, value) != 1)
				return ECGINVAL;

			return 0;
		}
	}

	return ECGROUPVALUENOTEXIST;
}

int cgroup_set_value_uint64(struct cgroup_controller *controller, const char *name,
			    u_int64_t value)
{
	int ret;
	int i;

	if (!controller || !name)
		return ECGINVAL;

	for (i = 0; i < controller->index; i++) {
		struct control_value *val = controller->values[i];

		if (!strcmp(val->name, name)) {
			ret = snprintf(val->value, sizeof(val->value), "%" PRIu64, value);
			if (ret >= sizeof(val->value))
				return ECGINVAL;

			val->dirty = true;
			return 0;
		}
	}

	return cgroup_add_value_uint64(controller, name, value);
}

int cgroup_get_value_bool(struct cgroup_controller *controller, const char *name, bool *value)
{
	int i;

	if (!controller || !name || !value)
		return ECGINVAL;

	for (i = 0; i < controller->index; i++) {
		struct control_value *val = controller->values[i];

		if (!strcmp(val->name, name)) {
			int cgc_val;

			if (sscanf(val->value, "%d", &cgc_val) != 1)
				return ECGINVAL;

			if (cgc_val)
				*value = true;
			else
				*value = false;

			return 0;
		}
	}

	return ECGROUPVALUENOTEXIST;
}

int cgroup_set_value_bool(struct cgroup_controller *controller, const char *name, bool value)
{
	int ret;
	int i;

	if (!controller || !name)
		return ECGINVAL;

	for (i = 0; i < controller->index; i++) {
		struct control_value *val = controller->values[i];

		if (!strcmp(val->name, name)) {
			if (value)
				ret = snprintf(val->value, sizeof(val->value), "1");
			else
				ret = snprintf(val->value, sizeof(val->value), "0");

			if (ret >= sizeof(val->value))
				return ECGINVAL;

			val->dirty = true;
			return 0;

		}
	}

	return cgroup_add_value_bool(controller, name, value);
}

struct cgroup *create_cgroup_from_name_value_pairs(const char *name,
						   struct control_value *name_value, int nv_number)
{
	struct cgroup_controller *cgc;
	struct cgroup *src_cgroup;
	char con[FILENAME_MAX];

	int ret;
	int i;

	/* create source cgroup */
	src_cgroup = cgroup_new_cgroup(name);
	if (!src_cgroup) {
		fprintf(stderr, "can't create cgroup: %s\n", cgroup_strerror(ECGFAIL));
		goto scgroup_err;
	}

	/* Add pairs name-value to relevant controllers of this cgroup. */
	for (i = 0; i < nv_number; i++) {

		if ((strchr(name_value[i].name, '.')) == NULL) {
			fprintf(stderr, "wrong -r  parameter (%s=%s)\n", name_value[i].name,
				name_value[i].value);
			goto scgroup_err;
		}

		strncpy(con, name_value[i].name, FILENAME_MAX - 1);
		con[FILENAME_MAX - 1] = '\0';

		strtok(con, ".");

		/*
		 * find out whether we have to add the controller or
		 * cgroup already contains it.
		 */
		cgc = cgroup_get_controller(src_cgroup, con);
		if (!cgc) {
			/* add relevant controller */
			cgc = cgroup_add_controller(src_cgroup, con);
			if (!cgc) {
				fprintf(stderr, "controller %s can't be add\n",	con);
				goto scgroup_err;
			}
		}

		/* add name-value pair to this controller */
		ret = cgroup_add_value_string(cgc, name_value[i].name, name_value[i].value);
		if (ret) {
			fprintf(stderr, "name-value pair %s=%s can't be set\n",	name_value[i].name,
				name_value[i].value);
			goto scgroup_err;
		}
	}

	return src_cgroup;

scgroup_err:
	cgroup_free(&src_cgroup);

	return NULL;
}

int cgroup_get_value_name_count(struct cgroup_controller *controller)
{
	if (!controller)
		return -1;

	return controller->index;
}


char *cgroup_get_value_name(struct cgroup_controller *controller, int index)
{

	if (!controller)
		return NULL;

	if (index < controller->index)
		return (controller->values[index])->name;
	else
		return NULL;
}

char *cgroup_get_cgroup_name(struct cgroup *cgroup)
{
	if (!cgroup)
		return NULL;

	return cgroup->name;
}


/*
 * Return true if cgroup setup mode is cgroup v1 (legacy), else
 * returns false.
 */
bool is_cgroup_mode_legacy(void)
{
       enum cg_setup_mode_t setup_mode;

       setup_mode = cgroup_setup_mode();
       return (setup_mode == CGROUP_MODE_LEGACY);
}

/*
 * Return true if cgroup setup mode is cgroup v1/v2 (hybrid), else
 * returns false.
 */
bool is_cgroup_mode_hybrid(void)
{
       enum cg_setup_mode_t setup_mode;

       setup_mode = cgroup_setup_mode();
       return (setup_mode == CGROUP_MODE_HYBRID);
}

/*
 * Return true if cgroup setup mode is cgroup v2 (unified), else
 * returns false.
 */
bool is_cgroup_mode_unified(void)
{
       enum cg_setup_mode_t setup_mode;

       setup_mode = cgroup_setup_mode();
       return (setup_mode == CGROUP_MODE_UNIFIED);
}
