/* SPDX-License-Identifier: LGPL-2.1-only */
#ifndef _LIBCGROUP_GROUPS_H
#define _LIBCGROUP_GROUPS_H

#ifndef _LIBCGROUP_H_INSIDE
#error "Only <libcgroup.h> should be included directly."
#endif

#ifndef SWIG
#include <features.h>
#include <sys/types.h>
#include <stdbool.h>
#endif

#ifdef __cplusplus
extern "C" {
#endif

enum cg_version_t {
	CGROUP_UNK = 0,
	CGROUP_V1,
	CGROUP_V2,
	CGROUP_DISK = 0xFF,
};

enum cg_setup_mode_t {
	CGROUP_MODE_UNK = 0,
	CGROUP_MODE_LEGACY,
	CGROUP_MODE_HYBRID,
	CGROUP_MODE_UNIFIED,
};

/**
 * Flags for cgroup_delete_cgroup_ext().
 */
enum cgroup_delete_flag {
	/**
	 * Ignore errors caused by migration of tasks to parent group.
	 */
	CGFLAG_DELETE_IGNORE_MIGRATION = 1,

	/**
	 * Recursively delete all child groups.
	 */
	CGFLAG_DELETE_RECURSIVE	= 2,

	/**
	 * Delete the cgroup only if it is empty, i.e. it has no subgroups and
	 * no processes inside. This flag cannot be used with
	 * CGFLAG_DELETE_RECURSIVE.
	 */
	CGFLAG_DELETE_EMPTY_ONLY = 4,
};

/**
 * @defgroup group_groups 2. Group manipulation API
 * @{
 *
 * @name Basic infrastructure
 * @{
 * <tt>struct cgroup*</tt> is the heart of @c libcgroup API.
 * The structure is opaque to applications, all access to the structure is
 * through appropriate functions.
 *
 * The most important information is that <b> one <tt>struct cgroup*</tt> can
 * represent zero, one or more real control groups in kernel</b>.
 * The <tt>struct cgroup*</tt> is identified by name of the group, which must be
 * set by cgroup_new_cgroup(). Multiple controllers (aka subsystems) can be
 * attached to one <tt>struct cgroup*</tt> using cgroup_add_controller(). These
 * controllers <b>can belong to different hierarchies</b>.
 *
 * This approach is different to the one in the Linux kernel - a control group
 * must be part of exactly one hierarchy there. In @c libcgroup, a group can be
 * part of multiple hierarchies, as long as the group name is the same.
 *
 * @par Example:
 * Let there be following control groups:
 * @code
 * cpu,cpuacct:/
 * cpu,cpuacct:/foo
 * cpu,cpuacct:/bar
 * freezer:/
 * freezer:/foo
 * @endcode
 * I.e. there is @c cpu and @c cpuacct controller mounted together in one
 * hierarchy, with @c foo and @c bar groups. In addition, @c freezer is
 * mounted as separate hierarchy, with only one @c foo group.
 *
 * @par
 * Following code creates <tt>struct cgroup*</tt> structure, which represents
 * one group <tt>cpu,cpuacct:/foo</tt>:
 * @code
 * struct cgroup *foo = cgroup_new_cgroup("foo");
 * cgroup_add_controller(foo, "cpu");
 * @endcode
 * Now, you can call e.g. cgroup_delete_cgroup() and the group is deleted from
 * the hierarchy. You can note that it's enough to add only one controller to
 * the group to fully identify a group in <tt>cpu,cpuacct</tt> hierarchy.
 *
 * @par
 * Following code creates <tt>struct cgroup*</tt> structure, which represents
 * @b two groups, <tt>cpu,cpuacct:/foo</tt> and <tt>freezer:/foo</tt>:
 * @code
 * struct cgroup *foo = cgroup_new_cgroup("foo");
 * cgroup_add_controller(foo, "cpu");
 * cgroup_add_controller(foo, "freezer");
 * @endcode
 * Now, if you call e.g. cgroup_delete_cgroup(), the group gets deleted from
 * @b both hierarchies.
 *
 * @todo add some propaganda what's so great on this approach... I personally
 * think it is broken and confusing (see TODOs below).
 *
 * Following functions are provided to create/destroy various libcgroup
 * structures. Please note that none of these functions actually create or
 * delete a cgroup in kernel!
 */

/**
 * @struct cgroup
 *
 * Structure describing one or more control groups. The structure is opaque to
 * applications.
 */
struct cgroup;

/**
 * @struct cgroup_controller
 * Structure describing a controller attached to one struct @c cgroup, including
 * parameters of the group and their values. The structure is opaque to
 * applications.
 * @see groups
 */
struct cgroup_controller;

/**
 * Uninitialized file/directory permissions used for task/control files.
 */
#define NO_PERMS (-1U)

/**
 * Uninitialized UID/GID used for task/control files.
 */
#define NO_UID_GID (-1U)

/**
 * Allocate new cgroup structure. This function itself does not create new
 * control group in kernel, only new <tt>struct cgroup</tt> inside libcgroup!
 *
 * @param name Path to the group, relative from root group. Use @c "/" or @c "."
 *	for the root group itself and @c "/foo/bar/baz" or @c "foo/bar/baz" for
 *	subgroups.
 *	@todo suggest one preferred way, either "/foo" or "foo".
 * @returns Created group or NULL on error.
 */
struct cgroup *cgroup_new_cgroup(const char *name);

/**
 * Attach new controller to cgroup. This function just modifies internal
 * libcgroup structure, not the kernel control group.
 *
 * @param cgroup
 * @param name The name of the controller, e.g. "freezer".
 * @return Created controller or NULL on error.
 */
struct cgroup_controller *cgroup_add_controller(struct cgroup *cgroup,
						const char *name);

/**
 * Attach all mounted controllers to given cgroup. This function just modifies
 * internal libcgroup structure, not the kernel control group.
 *
 * @param cgroup
 * @return zero or error number
 */
int cgroup_add_all_controllers(struct cgroup *cgroup);


/**
 * Return appropriate controller from given group.
 * The controller must be added before using cgroup_add_controller() or loaded
 * from kernel using cgroup_get_cgroup().
 * @param cgroup
 * @param name The name of the controller, e.g. "freezer".
 */
struct cgroup_controller *cgroup_get_controller(struct cgroup *cgroup,
						const char *name);

/**
 * Free internal @c cgroup structure. This function frees also all controllers
 * attached to the @c cgroup, including all parameters and their values.
 * @param cgroup
 */
void cgroup_free(struct cgroup **cgroup);

/**
 * Free internal list of controllers from the group.
 * @todo should this function be public???
 * @param cgroup
 */
void cgroup_free_controllers(struct cgroup *cgroup);

/**
 * @}
 * @name Group manipulation API
 * Using following functions you can create and remove control groups and
 * change their parameters.
 * @note All access to kernel is through previously mounted cgroup filesystems.
 * @c libcgroup does not mount/unmount anything for you.
 * @{
 */

/**
 * Physically create a control group in kernel. The group is created in all
 * hierarchies, which cover controllers added by cgroup_add_controller().
 * All parameters set by cgroup_add_value_* functions are written.
 * The created groups has owner which was set by cgroup_set_uid_gid() and
 * permissions set by cgroup_set_permissions.
 * @param cgroup
 * @param ignore_ownership When nozero, all errors are ignored when setting
 *	owner of the group and/or its tasks file.
 *	@todo what is ignore_ownership good for?
 * @retval #ECGROUPNOTEQUAL if not all specified controller parameters
 *      were successfully set.
 */
int cgroup_create_cgroup(struct cgroup *cgroup, int ignore_ownership);

/**
 * Physically create new control group in kernel, with all parameters and values
 * copied from its parent group. The group is created in all hierarchies, where
 * the parent group exists. I.e. following code creates subgroup in all
 * hierarchies, because all of them have root (=parent) group.
 * @code
 * struct cgroup *foo = cgroup_new_cgroup("foo");
 * cgroup_create_cgroup_from_parent(foo, 0);
 * @endcode
 * @todo what is this good for? Why the list of controllers added by
 * cgroup_add_controller() is not used, like in cgroup_create_cgroup()? I can't
 * crate subgroup of root group in just one hierarchy with this function!
 *
 * @param cgroup The cgroup to create. Only it's name is used, everything else
 *	is discarded.
 * @param ignore_ownership When nozero, all errors are ignored when setting
 *	owner of the group and/or its tasks file.
 *	@todo what is ignore_ownership good for?
 * @retval #ECGROUPNOTEQUAL if not all inherited controller parameters
 *      were successfully set (this is expected).
 */
int cgroup_create_cgroup_from_parent(struct cgroup *cgroup,
				     int ignore_ownership);

/**
 * Physically modify a control group in kernel. All parameters added by
 * cgroup_add_value_ or cgroup_set_value_ are written.
 * Currently it's not possible to change and owner of a group.
 *
 * @param cgroup
 */
int cgroup_modify_cgroup(struct cgroup *cgroup);

/**
 * Physically remove a control group from kernel. The group is removed from
 * all hierarchies,  which cover controllers added by cgroup_add_controller()
 * or cgroup_get_cgroup(). All tasks inside the group are automatically moved
 * to parent group.
 *
 * The group being removed must be empty, i.e. without subgroups. Use
 * cgroup_delete_cgroup_ext() for recursive delete.
 *
 * @param cgroup
 * @param ignore_migration When nozero, all errors are ignored when migrating
 *	tasks from the group to the parent group.
 *	@todo what is ignore_migration good for? rmdir() will fail if tasks were not moved.
 */
int cgroup_delete_cgroup(struct cgroup *cgroup, int ignore_migration);

/**
 * Physically remove a control group from kernel.
 * All tasks are automatically moved to parent group.
 * If #CGFLAG_DELETE_IGNORE_MIGRATION flag is used, the errors that occurred
 * during the task movement are ignored.
 * #CGFLAG_DELETE_RECURSIVE flag specifies that all subgroups should be removed
 * too. If root group is being removed with this flag specified, all subgroups
 * are removed but the root group itself is left undeleted.
 * @see cgroup_delete_flag.
 *
 * @param cgroup
 * @param flags Combination of CGFLAG_DELETE_* flags, which indicate what and
 *	how to delete.
 */
int cgroup_delete_cgroup_ext(struct cgroup *cgroup, int flags);

/**
 * @}
 * @name Other functions
 * @{
 * Helper functions to manipulate with control groups.
 */

/**
 * Read all information regarding the group from kernel.
 * Based on name of the group, list of controllers and all parameters and their
 * values are read from all hierarchies, where a group with given name exists.
 * All existing controllers are replaced. I.e. following code will fill @c root
 * with controllers from all hierarchies, because the root group is available in
 * all of them.
 * @code
 * struct cgroup *root = cgroup_new_cgroup("/");
 * cgroup_get_cgroup(root);
 * @endcode
 *
 * @todo what is this function good for? Why is not considered only the list of
 * controllers attached by cgroup_add_controller()? What owners will return
 * cgroup_get_uid_gid() if the group is in multiple hierarchies, each with
 * different owner of tasks file?
 *
 * @param cgroup The cgroup to load. Only it's name is used, everything else
 *	is replaced.
 */
int cgroup_get_cgroup(struct cgroup *cgroup);

/**
 * Copy all controllers, their parameters and values. Group name, permissions
 * and ownerships are not coppied. All existing controllers
 * in the source group are discarded.
 *
 * @param dst Destination group.
 * @param src Source group.
 */
int cgroup_copy_cgroup(struct cgroup *dst, struct cgroup *src);

/**
 * Compare names, owners, controllers, parameters and values of two groups.
 *
 * @param cgroup_a
 * @param cgroup_b
 *
 * @retval 0  if the groups are the same.
 * @retval #ECGROUPNOTEQUAL if the groups are not the same.
 * @retval #ECGCONTROLLERNOTEQUAL if the only difference are controllers,
 *	parameters or their values.
 */
int cgroup_compare_cgroup(struct cgroup *cgroup_a, struct cgroup *cgroup_b);


/**
 * Compare names, parameters and values of two controllers.
 *
 * @param cgca
 * @param cgcb
 *
 * @retval 0  if the controllers are the same.
 * @retval #ECGCONTROLLERNOTEQUAL if the controllers are not equal.
 */
int cgroup_compare_controllers(struct cgroup_controller *cgca,
			       struct cgroup_controller *cgcb);

/**
 * Set owner of the group control files and the @c tasks file. This function
 * modifies only @c libcgroup internal @c cgroup structure, use
 * cgroup_create_cgroup() afterwards to create the group with given owners.
 *
 * @param cgroup
 * @param tasks_uid UID of the owner of group's @c tasks file.
 * @param tasks_gid GID of the owner of group's @c tasks file.
 * @param control_uid UID of the owner of group's control files (i.e.
 *	parameters).
 * @param control_gid GID of the owner of group's control files (i.e.
 *	parameters).
 */
int cgroup_set_uid_gid(struct cgroup *cgroup, uid_t tasks_uid, gid_t tasks_gid,
		       uid_t control_uid, gid_t control_gid);

/**
 * Return owners of the group's @c tasks file and control files.
 * The data is read from @c libcgroup internal @c cgroup structure, use
 * cgroup_set_uid_gid() or cgroup_get_cgroup() to fill it.
 */
int cgroup_get_uid_gid(struct cgroup *cgroup, uid_t *tasks_uid,
		       gid_t *tasks_gid, uid_t *control_uid,
		       gid_t *control_gid);

/**
 * Stores given file permissions of the group's control and tasks files
 * into the @c cgroup data structure. Use NO_PERMS if permissions shouldn't
 * be changed or a value which applicable to chmod(2). Please note that
 * the given permissions are masked with the file owner's permissions.
 * For example if a control file has permissions 640 and control_fperm is
 * 471 the result will be 460.
 * @param cgroup
 * @param control_dperm Directory permission for the group.
 * @param control_fperm File permission for the control files.
 * @param task_fperm File permissions for task file.
 */
void cgroup_set_permissions(struct cgroup *cgroup,
			    mode_t control_dperm, mode_t control_fperm,
			    mode_t task_fperm);

/**
 * @}
 * @name Group parameters
 * These are functions can read or modify parameter of a group.
 * @note All these functions read/write parameters to @c libcgorup internal
 * structures. Use cgroup_get_cgroup() to load parameters from kernel to these
 * internal structures and cgroup_modify_cgroup() or cgroup_create_cgroup() to
 * write changes to kernel.
 * @{
 */

/**
 * Add parameter and its value to internal @c libcgroup structures.
 * Use cgroup_modify_cgroup() or cgroup_create_cgroup() to write it to kernel.
 *
 * @param controller
 * @param name The name of the parameter.
 * @param value
 *
 */
int cgroup_add_value_string(struct cgroup_controller *controller,
			    const char *name, const char *value);

/**
 * Add parameter and its value to internal @c libcgroup structures.
 * Use cgroup_modify_cgroup() or cgroup_create_cgroup() to write it to kernel.
 * Content of the value is copied to internal structures and is not needed
 * after return from the function.
 *
 * @param controller
 * @param name The name of the parameter.
 * @param value
 *
 */
int cgroup_add_value_int64(struct cgroup_controller *controller,
			   const char *name, int64_t value);

/**
 * Add parameter and its value to internal @c libcgroup structures.
 * Use cgroup_modify_cgroup() or cgroup_create_cgroup() to write it to kernel.
 *
 * @param controller
 * @param name The name of the parameter.
 * @param value
 *
 */
int cgroup_add_value_uint64(struct cgroup_controller *controller,
			    const char *name, u_int64_t value);

/**
 * Add parameter and its value to internal @c libcgroup structures.
 * Use cgroup_modify_cgroup() or cgroup_create_cgroup() to write it to kernel.
 *
 * @param controller
 * @param name The name of the parameter.
 * @param value
 *
 */
int cgroup_add_value_bool(struct cgroup_controller *controller,
			  const char *name, bool value);

/**
 * Read a parameter value from @c libcgroup internal structures.
 * Use @c cgroup_get_cgroup() to fill these structures with data from kernel.
 * It's up to the caller to free returned value.
 *
 * This function works only for 'short' parameters. Use
 * cgroup_read_stats_begin(), cgroup_read_stats_next() and
 * cgroup_read_stats_end() to read @c stats parameter, which can be longer
 * than libcgroup's internal buffers.
 * @todo rephrase, it's too vague... How big is the buffer actually?
 *
 * @param controller
 * @param name The name of the parameter.
 * @param value
 */
int cgroup_get_value_string(struct cgroup_controller *controller,
			    const char *name, char **value);
/**
 * Read a parameter value from @c libcgroup internal structures.
 * Use @c cgroup_get_cgroup() to fill these structures with data from kernel.
 *
 * @param controller
 * @param name The name of the parameter.
 * @param value
 */
int cgroup_get_value_int64(struct cgroup_controller *controller,
			   const char *name, int64_t *value);

/**
 * Read a parameter value from @c libcgroup internal structures.
 * Use @c cgroup_get_cgroup() to fill these structures with data from kernel.
 *
 * @param controller
 * @param name The name of the parameter.
 * @param value
 */
int cgroup_get_value_uint64(struct cgroup_controller *controller,
			    const char *name, u_int64_t *value);

/**
 * Read a parameter value from @c libcgroup internal structures.
 * Use @c cgroup_get_cgroup() to fill these structures with data from kernel.
 *
 * @param controller
 * @param name The name of the parameter.
 * @param value
 */
int cgroup_get_value_bool(struct cgroup_controller *controller,
			  const char *name, bool *value);

/**
 * Set a parameter value in @c libcgroup internal structures.
 * Use cgroup_modify_cgroup() or cgroup_create_cgroup() to write it to kernel.
 *
 * @param controller
 * @param name The name of the parameter.
 * @param value
 */
int cgroup_set_value_string(struct cgroup_controller *controller,
			    const char *name, const char *value);

/**
 * Set a parameter value in @c libcgroup internal structures.
 * Use cgroup_modify_cgroup() or cgroup_create_cgroup() to write it to kernel.
 * Content of the value is copied to internal structures and is not needed
 * after return from the function.
 *
 * @param controller
 * @param name The name of the parameter.
 * @param value
 */
int cgroup_set_value_int64(struct cgroup_controller *controller,
			   const char *name, int64_t value);
/**
 * Set a parameter value in @c libcgroup internal structures.
 * Use cgroup_modify_cgroup() or cgroup_create_cgroup() to write it to kernel.
 *
 * @param controller
 * @param name The name of the parameter.
 * @param value
 */
int cgroup_set_value_uint64(struct cgroup_controller *controller,
			    const char *name, u_int64_t value);

/**
 * Set a parameter value in @c libcgroup internal structures.
 * Use cgroup_modify_cgroup() or cgroup_create_cgroup() to write it to kernel.
 *
 * @param controller
 * @param name The name of the parameter.
 * @param value
 */
int cgroup_set_value_bool(struct cgroup_controller *controller,
			  const char *name, bool value);

/**
 * Return the number of variables for the specified controller in @c libcgroup
 * internal structures. Use cgroup_get_cgroup() to fill these structures with
 * data from kernel. Use this function together with cgroup_get_value_name()
 * to list all parameters of a group.
 *
 * @param controller
 * @return Count of the parameters or -1 on error.
 */
int cgroup_get_value_name_count(struct cgroup_controller *controller);

/**
 * Return the name of parameter of controller at given index.
 * The index goes from 0 to cgroup_get_value_name_count()-1.
 * Use this function to list all parameter of the controller.
 *
 * @note The returned value is pointer to internal @c libcgroup structure,
 * do not free it.
 *
 * @param controller
 * @param index The index of the parameter.
 * @return Name of the parameter.
 */
char *cgroup_get_value_name(struct cgroup_controller *controller, int index);

/**
 * Get the list of process in a cgroup. This list is guaranteed to
 * be sorted. It is not necessary that it is unique.
 * @param name The name of the cgroup
 * @param controller The name of the controller
 * @param pids The list of pids. Should be uninitialized when passed
 * to the API. Should be freed by the caller using free.
 * @param size The size of the pids array returned by the API.
 */
int cgroup_get_procs(char *name, char *controller, pid_t **pids, int *size);

/**
 * Change permission of files and directories of given group
 * @param cgroup The cgroup which permissions should be changed
 * @param dir_mode The permission mode of group directory
 * @param dirm_change Denotes whether the directory change should be done
 * @param file_mode The permission mode of group files
 * @param filem_change Denotes whether the directory change should be done
 */
int cg_chmod_recursive(struct cgroup *cgroup, mode_t dir_mode,
		       int dirm_change, mode_t file_mode, int filem_change);

/**
 *  Get the name of the cgroup from a given cgroup
 *  @param cgroup The cgroup whose name is needed
 */
char *cgroup_get_cgroup_name(struct cgroup *cgroup);

/*
 * Convert from one cgroup version to another version
 *
 * @param out_cgroup Destination cgroup
 * @param out_version Destination cgroup version
 * @param in_cgroup Source cgroup
 * @param in_version Source cgroup version, only used if set to v1 or v2
 *
 * @return 0 on success
 *         ECGFAIL conversion failed
 *         ECGCONTROLLERNOTEQUAL incorrect controller version provided
 */
int cgroup_convert_cgroup(struct cgroup * const out_cgroup,
			  enum cg_version_t out_version,
			  const struct cgroup * const in_cgroup,
			  enum cg_version_t in_version);

/**
 * List the mount paths, that matches the specified version
 *
 *	@param cgrp_version The cgroup type/version
 *	@param mount_paths Holds the list of mount paths
 *	@return 0 success and list of mounts paths in mount_paths
 *		ECGOTHER on failure and mount_paths is NULL.
 */
int cgroup_list_mount_points(const enum cg_version_t cgrp_version,
			     char ***mount_paths);

/**
 * Get the cgroup version of a controller.  Version is set to CGROUP_UNK
 * if the version cannot be determined.
 *
 * @param controller The controller of interest
 * @param version The version of the controller
 */
int cgroup_get_controller_version(const char * const controller,
				  enum cg_version_t * const version);

/**
 * Get the current group setup mode (legacy/unified/hybrid)
 *
 * @return CGROUP_MODE_UNK on failure and setup mode on success
 */
enum cg_setup_mode_t cgroup_setup_mode(void);

/**
 * Return the number of controllers for the specified cgroup in libcgroup
 * internal structures.
 *
 * @param cgroup
 * @return Count of the controllers or -1 on error.
 */
int cgroup_get_controller_count(struct cgroup *cgroup);

/**
 * Return requested controller from given group
 *
 * @param cgroup
 * @param index The index into the cgroup controller list
 */
struct cgroup_controller *cgroup_get_controller_by_index(struct cgroup *cgroup, int index);

/**
 * Given a controller pointer, get the name of the controller
 *
 * @param controller
 * @return controller name string, NULL if there's an error
 */
char *cgroup_get_controller_name(struct cgroup_controller *controller);

/**
 * Return true if cgroup setup mode is cgroup v1 (legacy), else
 * returns false.
 */
bool is_cgroup_mode_legacy(void);

/**
 * Return true if cgroup setup mode is cgroup v1/v2 (hybrid), else
 * returns false.
 */
bool is_cgroup_mode_hybrid(void);

/**
 * Return true if cgroup setup mode is cgroup v2 (unified), else
 * returns false.
 */
bool is_cgroup_mode_unified(void);

/**
 * @}
 * @}
 */


#ifdef __cplusplus
} /* extern "C" */
#endif

#endif /* _LIBCGROUP_GROUPS_H */
