/* SPDX-License-Identifier: LGPL-2.1-only */
/**
 * Copyright Red Hat, Inc. 2009
 *
 * Author:	Vivek Goyal <vgoyal@redhat.com>
 *		Jan Safranek <jsafrane@redhat.com>
 */

#ifndef __TOOLS_COMMON

#define __TOOLS_COMMON

#ifdef __cplusplus
extern "C" {
#endif

#include "config.h"
#include "../libcgroup-internal.h"

#include <libcgroup.h>

#define cgroup_err(x...)	cgroup_log(CGROUP_LOG_ERROR, "Error: " x)
#define cgroup_warn(x...)	cgroup_log(CGROUP_LOG_WARNING, "Warning: " x)
#define cgroup_info(x...)	cgroup_log(CGROUP_LOG_INFO, "Info: " x)
#define cgroup_dbg(x...)	cgroup_log(CGROUP_LOG_DEBUG, x)
#define cgroup_cont(x...)	cgroup_log(CGROUP_LOG_CONT, x)

#define err(x...)	fprintf(stderr, x)
#define info(x...)	fprintf(stdout, x)

#define EXIT_BADARGS	129

/**
 * Auxiliary specifier of group, used to store parsed command line options.
 */
struct cgroup_group_spec {
	char path[FILENAME_MAX];
	char *controllers[CG_CONTROLLER_MAX];
};

/**
 * Simple dynamic array of strings.
 */
struct cgroup_string_list {
	char **items;
	int size;
	int count;
};

/**
 * Parse command line option with group specifier into provided data structure.
 * The option must have form of 'controller1,controller2,..:group_name'.
 *
 * The parsed list of controllers and group name is added at the end of
 * provided cdptr,
 * i.e. on place of first NULL cgroup_group_spec*.
 *
 * @param cdptr Target data structure to fill. New item is allocated and
 *	added at the end.
 * @param optarg Argument to parse.
 * @param capacity The capacity of the cdptr array.
 * @return 0 on success, != 0 on error.
 */
int parse_cgroup_spec(struct cgroup_group_spec **cdptr, char *optarg, int capacity);

/**
 * Free a single cgroup_group_spec structure.
 *	@param cl The structure to free from memory
 */
void cgroup_free_group_spec(struct cgroup_group_spec *cl);

/**
 * Initialize a new list.
 * @param list The list to initialize.
 * @param initial_size The initial size of the list to pre-allocate.
 */
int cgroup_string_list_init(struct cgroup_string_list *list, int initial_size);

/**
 * Destroy a list, automatically freeing all its items.
 * @param list The list to destroy.
 */
void cgroup_string_list_free(struct cgroup_string_list *list);

/**
 * Adds new item to the list. It automatically resizes underlying array if needed.
 * @param list The list to modify.
 * @param item The item to add. The item is automatically copied to new buffer.
 */
int cgroup_string_list_add_item(struct cgroup_string_list *list, const char *item);

/**
 * Add alphabetically sorted files present in given directory
 * (without subdirs) to list of strings.
 * The function exits on error.
 * @param list The list to add files to.
 * @param dirname Full path to directory to examime.
 * @param program_name Name of the executable, it will be used for
 *	printing errors to stderr.
 */
int cgroup_string_list_add_directory(struct cgroup_string_list *list, char *dirname,
				     char *program_name);

/**
 * Parse file permissions as octal number.
 * @param string A string to parse, must contain 3-4 characters '0'-'7'.
 * @param pmode Parsed mode.
 * @oaram program_name Argv[0] to show error messages.
 */
int parse_mode(char *string, mode_t *pmode, const char *program_name);

/**
 * Parse UID and GID from string in form "user:group".
 * @param string A string to parse.
 * @param uid Parsed UID (-1 if 'user' is missing in the string).
 * @param gid Parsed GID (-1 if 'group' is missing in the string).
 * @param program_name Argv[0] to show error messages.
 */
int parse_uid_gid(char *string, uid_t *uid, gid_t *gid, const char *program_name);

/**
 * Functions that are defined as STATIC can be placed within the
 * UNIT_TEST ifdef.  This will allow them to be included in the unit tests
 * while remaining static in a normal libcgroup build.
 */
#ifdef UNIT_TEST

int parse_r_flag(const char * const program_name, const char * const name_value_str,
		 struct control_value * const name_value);

#endif /* UNIT_TEST */

#ifdef __cplusplus
} /* extern "C" */
#endif

#endif /* TOOLS_COMMON */
