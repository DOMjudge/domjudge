// SPDX-License-Identifier: LGPL-2.1-only
/**
 * Copyright Red Hat, Inc. 2009
 *
 * Author:	Vivek Goyal <vgoyal@redhat.com>
 *		Jan Safranek <jsafrane@redhat.com>
 */

/* for asprintf */
#define _GNU_SOURCE

#include "tools-common.h"

#include <libcgroup.h>

#include <string.h>
#include <stdlib.h>
#include <dirent.h>
#include <errno.h>
#include <stdio.h>
#include <pwd.h>
#include <grp.h>

#include <sys/types.h>

int parse_cgroup_spec(struct cgroup_group_spec **cdptr, char *optarg, int capacity)
{
	char *cptr = NULL, *pathptr = NULL, *temp;
	struct cgroup_group_spec *ptr;
	int i, j;

	ptr = *cdptr;

	/* Find first free entry inside the cgroup data array */
	for (i = 0; i < capacity; i++, ptr++) {
		if (!cdptr[i])
			break;
	}

	if (i == capacity) {
		/* No free slot found */
		fprintf(stderr, "Max allowed hierarchies %d reached\n", capacity);
		return -1;
	}

	/* Extract list of controllers */
	if (optarg[0] == ':') {
		/* No controller was passed in */
		cptr = NULL;
		pathptr = strtok(optarg, ":");
	} else {
		/* Extract the list of controllers from the user */
		cptr = strtok(optarg, ":");
		cgroup_dbg("list of controllers is %s\n", cptr);
		if (!cptr)
			return -1;

		pathptr = strtok(NULL, ":");
	}

	cgroup_dbg("cgroup path is %s\n", pathptr);
	if (!pathptr)
		return -1;

	/* instanciate cgroup_data. */
	cdptr[i] = calloc(1, sizeof(struct cgroup_group_spec));
	if (!cdptr[i]) {
		fprintf(stderr, "%s\n", strerror(errno));
		return -1;
	}

	if (cptr != NULL) {
		/* Convert list of controllers into an array of strings. */
		j = 0;
		do {
			if (j == 0)
				temp = strtok(cptr, ",");
			else
				temp = strtok(NULL, ",");

			if (temp) {
				cdptr[i]->controllers[j] = strdup(temp);
				if (!cdptr[i]->controllers[j]) {
					free(cdptr[i]);
					fprintf(stderr, "%s\n", strerror(errno));
					return -1;
				}
			}
			j++;
		} while (temp && j < CG_CONTROLLER_MAX-1);
	}

	/* Store path to the cgroup */
	strncpy(cdptr[i]->path, pathptr, FILENAME_MAX - 1);
	cdptr[i]->path[sizeof(cdptr[i]->path) - 1] = '\0';

	return 0;
}


/**
 * Free a single cgroup_group_spec structure
 * <--->@param cl The structure to free from memory.
 */
void cgroup_free_group_spec(struct cgroup_group_spec *cl)
{
	/* Loop variable */
	int i = 0;

	/* Make sure our structure is not NULL, first. */
	if (!cl) {
		cgroup_dbg("Warning: Attempted to free NULL rule.\n");
		return;
	}

	/* We must free any used controller strings, too. */
	for (i = 0; i < CG_CONTROLLER_MAX; i++) {
		if (cl->controllers[i])
			free(cl->controllers[i]);
	}

	free(cl);
}


int cgroup_string_list_init(struct cgroup_string_list *list, int initial_size)
{
	if (list == NULL)
		return ECGINVAL;

	list->items = calloc(initial_size, sizeof(char *));
	if (!list->items)
		return ECGFAIL;

	list->count = 0;
	list->size = initial_size;

	return 0;
}

void cgroup_string_list_free(struct cgroup_string_list *list)
{
	int i;

	if (list == NULL)
		return;

	if (list->items == NULL)
		return;

	for (i = 0; i < list->count; i++)
		free(list->items[i]);

	free(list->items);
}

int cgroup_string_list_add_item(struct cgroup_string_list *list, const char *item)
{
	if (list == NULL)
		return ECGINVAL;

	if (list->size <= list->count) {
		char **tmp = realloc(list->items, sizeof(char *) * list->size*2);
		if (tmp == NULL)
			return ECGFAIL;
		list->items = tmp;
		list->size = list->size * 2;
	}

	list->items[list->count] = strdup(item);
	if (list->items[list->count] == NULL)
		return ECGFAIL;
	list->count++;

	return 0;
}

static int _compare_string(const void *a, const void *b)
{
	const char *sa = * (char * const *) a;
	const char *sb = * (char * const *) b;

	return strcmp(sa, sb);
}


int cgroup_string_list_add_directory(struct cgroup_string_list *list, char *dirname,
				     char *program_name)
{
	int start, ret, count = 0;
	struct dirent *item;
	DIR *d;

	if (list == NULL)
		return ECGINVAL;

	start = list->count;

	d = opendir(dirname);
	if (!d) {
		fprintf(stderr, "%s: cannot open %s: %s\n", program_name, dirname,
			strerror(errno));
		exit(1);
	}

	do {
		errno = 0;
		item = readdir(d);
		if (item && (item->d_type == DT_REG || item->d_type == DT_LNK)) {
			char *tmp, *file_ext;

			/* we are interested in .conf files, skip others */
			file_ext = strstr(item->d_name, ".conf");
			if (!file_ext)
				continue;

			if (strcmp(file_ext, ".conf") || strlen(item->d_name) == 5)
				continue;

			ret = asprintf(&tmp, "%s/%s", dirname, item->d_name);
			if (ret < 0) {
				fprintf(stderr, "%s: out of memory\n", program_name);
				exit(1);
			}
			ret = cgroup_string_list_add_item(list, tmp);
			free(tmp);
			count++;
			if (ret) {
				fprintf(stderr, "%s: %s\n", program_name, cgroup_strerror(ret));
				exit(1);
			}
		}
		if (!item && errno) {
			fprintf(stderr, "%s: cannot read %s: %s\n", program_name, dirname,
				strerror(errno));
			exit(1);
		}
	} while (item != NULL);
	closedir(d);

	/* sort the names found in the directory */
	if (count > 0)
		qsort(&list->items[start], count, sizeof(char *), _compare_string);

	return 0;
}

/* allowed mode strings are octal version: "755" */
int parse_mode(char *string, mode_t *pmode, const char *program_name)
{
	size_t len;
	char *end;
	long mode;

	len = strlen(string);
	if (len < 3 || len > 4)
		goto err;

	errno = 0;
	mode = strtol(string, &end, 8);
	if (errno != 0 || *end != '\0')
		goto err;
	*pmode = mode;
	return 0;

err:
	*pmode = 0;
	fprintf(stdout, "%s wrong mode format %s\n", program_name, string);

	return -1;
}

int parse_uid_gid(char *string, uid_t *uid, gid_t *gid, const char *program_name)
{
	char *grp_string = NULL;
	char *pwd_string = NULL;
	struct passwd *pwd;
	struct group *grp;

	*uid = *gid = NO_UID_GID;

	if (string[0] == ':')
		grp_string = strtok(string, ":");
	else {
		pwd_string = strtok(string, ":");
		if (pwd_string != NULL)
			grp_string = strtok(NULL, ":");
	}

	if (pwd_string != NULL) {
		pwd = getpwnam(pwd_string);
		if (pwd != NULL) {
			*uid = pwd->pw_uid;
		} else {
			fprintf(stderr, "%s: can't find uid of user %s.\n", program_name,
				pwd_string);
			return -1;
		}
	}
	if (grp_string != NULL) {
		grp = getgrnam(grp_string);
		if (grp != NULL) {
			*gid = grp->gr_gid;
		} else {
			fprintf(stderr, "%s: can't find gid of group %s.\n", program_name,
				grp_string);
			return -1;
		}
	}

	return 0;
}
