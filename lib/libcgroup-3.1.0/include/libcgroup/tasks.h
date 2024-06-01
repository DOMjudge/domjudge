/* SPDX-License-Identifier: LGPL-2.1-only */
#ifndef _LIBCGROUP_TASKS_H
#define _LIBCGROUP_TASKS_H

#ifndef _LIBCGROUP_H_INSIDE
#error "Only <libcgroup.h> should be included directly."
#endif

#include <libcgroup/groups.h>

#ifndef SWIG
#include <features.h>
#include <stdbool.h>
#endif

#ifdef __cplusplus
extern "C" {
#endif

/** Flags for cgroup_change_cgroup_uid_gid(). */
enum cgflags {
	/** Use cached rules, do not read rules from disk. */
	CGFLAG_USECACHE = 0x01,
	/** Use cached templates, do not read templates from disk. */
	CGFLAG_USE_TEMPLATE_CACHE = 0x02,
};

/** Flags for cgroup_register_unchanged_process(). */
enum cgroup_daemon_type {
	/**
	 * The daemon must not touch the given task, i.e. it never moves it
	 * to any controlgroup.
	 */
	CGROUP_DAEMON_UNCHANGE_CHILDREN       = 0x1,
	CGROUP_DAEMON_CANCEL_UNCHANGE_PROCESS = 0x2,
};

/**
 * @defgroup group_tasks 4. Manipulation with tasks
 * @{
 *
 * @name Simple task assignment
 * @{
 * Applications can use following functions to simply put a task into given
 * control group and find a groups where given tasks is.
 */

/**
 * Move current task (=thread) to given control group.
 * @param cgroup Destination control group.
 */
int cgroup_attach_task(struct cgroup *cgroup);

/**
 * Move given task (=thread) to given control group.
 * @param cgroup Destination control group.
 * @param tid The task to move.
 */
int cgroup_attach_task_pid(struct cgroup *cgroup, pid_t tid);

/**
 * Changes the cgroup of a task based on the path provided.  In this case,
 * the user must already know into which cgroup the task should be placed and
 * no rules will be parsed.
 *
 * @param path Name of the destination group.
 * @param pid The task to move.
 * @param controllers List of controllers.
 *
 * @todo should this function be really public?
 */
int cgroup_change_cgroup_path(const char *path, pid_t pid,
			      const char * const controllers[]);

/**
 * Get the current control group path where the given task is.
 * @param pid The task to find.
 * @param controller The controller (hierarchy), where to find the task.
 * @param current_path The path to control group, where the task has been found.
 *	The patch is relative to the root of the hierarchy. The caller must
 *	free this memory.
 */
int cgroup_get_current_controller_path(pid_t pid, const char *controller,
				       char **current_path);

/**
 * @}
 *
 * @name Rules
 * @{
 * @c libcgroup can move tasks to control groups using simple rules, loaded
 * from configuration file. See cgrules.conf man page to see format of the file.
 * Following functions can be used to load these rules from a file.
 */

/**
 * Initializes the rules cache and load it from /etc/cgrules.conf.
 * @todo add parameter with the filename?
 */
int cgroup_init_rules_cache(void);

/**
 * Reloads the rules list from /etc/cgrules.conf. This function
 * is probably NOT thread safe (calls cgroup_parse_rules_config()).
 */
int cgroup_reload_cached_rules(void);

/**
 * Print the cached rules table.  This function should be called only after
 * first calling cgroup_parse_config(), but it will work with an empty rule
 * list.
 * @param fp Destination file, where the rules will be printed.
 */
void cgroup_print_rules_config(FILE *fp);

/**
 * @}
 * @name Rule based task assignment
 * @{
 * @c libcgroup can move tasks to control groups using simple rules, loaded
 * from configuration file. See cgrules.conf man page to see format of the file.
 * Applications can move tasks to control groups based on these rules using
 * following functions.
 */

/**
 * Changes the cgroup of all running PIDs based on the rules in the config
 * file. If a rules exists for a PID, then the PID is placed in the correct
 * group.
 *
 * This function may be called after creating new control groups to move
 * running PIDs into the newly created control groups.
 *	@return 0 on success, < 0 on error
 */
int cgroup_change_all_cgroups(void);

/**
 * Changes the cgroup of a program based on the rules in the config file.
 * If a rule exists for the given UID, GID or PROCESS NAME, then the given
 * PID is placed into the correct group.  By default, this function parses
 * the configuration file each time it is called.
 *
 * The flags can alter the behavior of this function:
 *	CGFLAG_USECACHE: Use cached rules instead of parsing the config file
 *      CGFLAG_USE_TEMPLATE_CACHE: Use cached templates instead of
 * parsing the config file
 *
 * This function may NOT be thread safe.
 * @param uid The UID to match.
 * @param gid The GID to match.
 * @param procname The PROCESS NAME to match.
 * @param pid The PID of the process to move.
 * @param flags Bit flags to change the behavior, as defined in enum #cgflags.
 * @todo Determine thread-safeness and fix of not safe.
 */
int cgroup_change_cgroup_flags(uid_t uid, gid_t gid,
			       const char *procname, pid_t pid, int flags);

/**
 * Changes the cgroup of a program based on the rules in the config file.  If a
 * rule exists for the given UID or GID, then the given PID is placed into the
 * correct group.  By default, this function parses the configuration file each
 * time it is called.
 *
 * This function may NOT be thread safe.
 * @param uid The UID to match.
 * @param gid The GID to match.
 * @param pid The PID of the process to move.
 * @param flags Bit flags to change the behavior, as defined in enum #cgflags.
 * @todo Determine thread-safeness and fix if not safe.
 */
int cgroup_change_cgroup_uid_gid_flags(uid_t uid, gid_t gid,
				       pid_t pid, int flags);

/**
 * Provides backwards-compatibility with older versions of the API.  This
 * function is deprecated, and cgroup_change_cgroup_uid_gid_flags() should be
 * used instead.  In fact, this function simply calls the newer one with flags
 * set to 0 (none).
 * @param uid The UID to match.
 * @param gid The GID to match.
 * @param pid The PID of the process to move.
 */
int cgroup_change_cgroup_uid_gid(uid_t uid, gid_t gid, pid_t pid);

/**
 * @}
 * @name Communication with cgrulesengd daemon
 * @{
 * Users can use cgrulesengd daemon to move tasks to groups based on the rules
 * automatically when they change their UID, GID or executable name.
 * The daemon allows tasks to be 'sticky', i.e. all rules are ignored for these
 * tasks and the daemon never moves them.
 */

/**
 * Register the unchanged process to a cgrulesengd daemon. This process
 * is never moved to another control group by the daemon.
 * If the daemon does not work, this function returns 0 as success.
 * @param pid The task id.
 * @param flags Bit flags to change the behavior, as defined in
 *	#cgroup_daemon_type
 */
int cgroup_register_unchanged_process(pid_t pid, int flags);

/**
 * @}
 * @}
 */
#ifdef __cplusplus
} /* extern "C" */
#endif

#endif /* _LIBCGROUP_TASKS_H */
