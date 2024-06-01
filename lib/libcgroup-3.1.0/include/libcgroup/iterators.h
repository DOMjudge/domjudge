/* SPDX-License-Identifier: LGPL-2.1-only */
#ifndef _LIBCGROUP_ITERATORS_H
#define _LIBCGROUP_ITERATORS_H

#ifndef _LIBCGROUP_H_INSIDE
#error "Only <libcgroup.h> should be included directly."
#endif

#ifndef SWIG
#include <sys/types.h>
#include <stdio.h>
#include <features.h>
#endif

#ifdef __cplusplus
extern "C" {
#endif

/**
 * @defgroup group_iterators 3. Iterators
 * @{
 * So-called iterators are a code pattern to retrieve various data from
 * libcgroup in distinct chunks, for example when an application needs to read
 * list of groups in a hierarchy, it uses iterator to get one group at a time.
 * Iterator is opaque to the application, the application sees only
 * <tt>void* handle</tt> pointer, which is managed internally by @c libcgroup.
 * Each iterator provides at least these functions:
 * - <tt>int <i>iterator_name</i>_begin(void **handle, my_type *item)</tt>
 *     - Initialize the iterator, store pointer to it into the @c handle.
 *     - Return the first element in the iterator, let's say it's @c my_type.
 *     - Return @c 0, if the operation succeeded.
 *     - Return #ECGEOF, if the operation succeeded, but the iterator is empty.
 *       The value of @c item is undefined in this case.
 *     - Return any other error code on error.
 * - <tt>int <i>iterator_name</i>_next(void **handle, my_type *item)</tt>
 *     - Advance to next element in the iterator and return it.
 *     - Return @c 0, if the operation succeeded.
 *     - Return #ECGEOF, if there is no item to advance to, i.e. the iterator
 *       is already at its end. The value of @c item is undefined in this case.
 *     - Return any other error code on error.
 * - <tt>void <i>iterator_name</i>_end(void **handle)</tt>
 *     - Free any data associated with the iterator. This function must be
 *     called even when <tt><i>iterator_name</i>_begin()</tt> fails.
 *
 * @todo not all iterators follow this pattern, e.g. cgroup_walk_tree_begin()
 * can result both in a state that  cgroup_walk_tree_end() is not needed
 * and will sigsegv and in a state that cgroup_walk_tree_end() is needed
 * to free allocated memory. Complete review is needed!
 * @par Example of iterator usage:
 * @code
 * void *handle; // our iterator handle
 * my_type item; // the data returned by the iterator
 * int ret;
 * ret = iterator_name_begin(&handle, &item);
 * while (ret == 0) {
 *	// process the item here
 *	ret = iterator_name_begin(&handle, &item);
 * }
 * if (ret != ECGEOF) {
 *	// process the error here
 * }
 * iterator_name_end(&handle);
 * @endcode
 *
 * @name Walk through control group filesystem
 * @{
 * This iterator returns all subgroups of given control group. It can be used
 * to return all groups in given hierarchy, when root control group is provided.
 */

/**
 * Type of the walk.
 */
enum cgroup_walk_type {
	/**
	 * Pre-order directory walk, return a directory first and then its
	 * subdirectories.
	 * E.g. directories would be returned in this order:
	 * @code
	 * /
	 * /group
	 * /group/subgroup1
	 * /group/subgroup1/subsubgroup
	 * /group/subgroup2
	 * @endcode
	 */
	CGROUP_WALK_TYPE_PRE_DIR = 0x1,
	/**
	 * Post-order directory walk, return subdirectories of a directory
	 * first and then the directory itself.
	 * E.g. directories would be returned in this order:
	 * @code
	 * /group/subgroup1/subsubgroup
	 * /group/subgroup1
	 * /group/subgroup2
	 * /group
	 * /
	 * @endcode
	 */
	CGROUP_WALK_TYPE_POST_DIR = 0x2,
};

/**
 * Type of returned entity.
 */
enum cgroup_file_type {
	CGROUP_FILE_TYPE_FILE,		/**< File. */
	CGROUP_FILE_TYPE_DIR,		/**< Directory. */
	CGROUP_FILE_TYPE_OTHER,		/**< Directory. @todo really? */
};

/**
 * Information about found directory (= a control group).
 */
struct cgroup_file_info {
	/** Type of the entity. */
	enum cgroup_file_type type;
	/** Name of the entity. */
	const char *path;
	/** Name of its parent. */
	const char *parent;
	/**
	 * Full path to the entity. To get path relative to the root of the
	 * walk, you must store its @c full_path (or its length)
	 * and calculate the relative path by yourself.
	 */
	const char *full_path;
	/**
	 * Depth of the entity, how many directories below the root of
	 * walk it is.
	 */
	short depth;
};

/**
 * Walk through the directory tree for the specified controller.
 * The directory representing @c base_path is returned in @c info.
 * Use cgroup_walk_tree_set_flags() to specify, in which order should be next
 * directories returned.
 * @param controller Name of the controller, for which we want to walk
 * the directory tree.
 * @param base_path Begin walking from this path. Use "/" to walk through
 * full hierarchy.
 * @param depth The maximum depth to which the function should walk, 0
 * implies all the way down.
 * @param handle The handle to be used during iteration.
 * @param info The info filled and returned about directory information.
 * @param base_level Opaque integer which you must pass to subsequent
 *	cgroup_walk_tree_next.
 * @todo why base_level is not hidden in **handle?
 * @return #ECGEOF when there is no node.
 */
int cgroup_walk_tree_begin(const char *controller, const char *base_path, int depth,
			   void **handle, struct cgroup_file_info *info,
			   int *base_level);

/**
 * Get the next directory in the walk.
 * @param depth The maximum depth to which the function should walk, 0
 * implies all the way down.
 * @param handle The handle to be used during iteration.
 * @param info The info filled and returned about the next directory.
 * @param base_level Value of base_level returned by cgroup_walk_tree_begin().
 * @return #ECGEOF when we are done walking through the nodes.
 */
int cgroup_walk_tree_next(int depth, void **handle,
			  struct cgroup_file_info *info, int base_level);

/**
 * Release the iterator.
 */
int cgroup_walk_tree_end(void **handle);

/**
 * Set the flags for walk_tree. Currently available flags are in
 * #cgroup_walk_type enum.
 * @param handle The handle of the iterator.
 * @param flags
 */
int cgroup_walk_tree_set_flags(void **handle, int flags);

/**
 * Read the value of the given variable for the specified
 * controller and control group.
 * The value is read up to newline character or at most max-1 characters,
 * whichever comes first (i.e. similar to fgets()).
 * @param controller Name of the controller for which stats are requested.
 * @param path The path to control group, relative to hierarchy root.
 * @param name is variable name.
 * @param handle The handle to be used during iteration.
 * @param buffer The buffer to read the value into.
 * The buffer is always zero-terminated.
 * @param max Maximal lenght of the buffer
 * @return #ECGEOF when the stats file is empty.
 */

int cgroup_read_value_begin(const char * const controller, const char *path,
			    const char * const name, void **handle,
			    char *buffer, int max);

/**
 * Read the next string from the given variable handle
 * which is generated by cgroup_read_stats_begin() function.
 * the value is read up to newline character or at most max-1 characters,
 * whichever comes first (i.e. similar to fgets()) per
 * cgroup_read_stats_next() call
 * @param handle The handle to be used during iteration.
 * @param data returned the string.
 * @param buffer The buffer to read the value into.
 * The buffer is always zero-terminated.
 * @param max Maximal lenght of the buffer
 * @return #ECGEOF when the iterator finishes getting the list of stats.
 */
int cgroup_read_value_next(void **handle, char *buffer, int max);

/**
 * Release the iterator.
 */
int cgroup_read_value_end(void **handle);

/**
 * @}
 *
 * @name Read group stats
 * libcgroup's cgroup_get_value_string() reads only relatively short parametrs
 * of a group. Use following functions to read @c stats parameter, which can
 * be quite long.
 */

/**
 * Maximum length of a value in stats file.
 */
#define CG_VALUE_MAX 100
/**
 * One item in stats file.
 */
struct cgroup_stat {
	char name[FILENAME_MAX];
	char value[CG_VALUE_MAX];
};

/**
 * Read the statistics values (= @c stats parameter) for the specified
 * controller and control group. One line is returned per
 * cgroup_read_stats_begin() and cgroup_read_stats_next() call.
 * @param controller Name of the controller for which stats are requested.
 * @param path The path to control group, relative to hierarchy root.
 * @param handle The handle to be used during iteration.
 * @param stat Returned first item in the stats file.
 * @return #ECGEOF when the stats file is empty.
 */
int cgroup_read_stats_begin(const char *controller, const char *path, void **handle,
			    struct cgroup_stat *stat);

/**
 * Read the next stat value.
 * @param handle The handle to be used during iteration.
 * @param stat Returned next item in the stats file.
 * @return #ECGEOF when the iterator finishes getting the list of stats.
 */
int cgroup_read_stats_next(void **handle, struct cgroup_stat *stat);

/**
 * Release the iterator.
 */
int cgroup_read_stats_end(void **handle);

/**
 * @}
 *
 * @name List all tasks in a group
 * Use following functions to read @c tasks file of a group.
 * @{
 */

/**
 * Read the tasks file to get the list of tasks in a cgroup.
 * @param cgroup Name of the cgroup.
 * @param controller Name of the cgroup subsystem.
 * @param handle The handle to be used in the iteration.
 * @param pid The pid read from the tasks file.
 * @return #ECGEOF when the group does not contain any tasks.
 */
int cgroup_get_task_begin(const char *cgroup, const char *controller, void **handle,
			  pid_t *pid);

/**
 * Read the next task value.
 * @param handle The handle used for iterating.
 * @param pid The variable where the value will be stored.
 *
 * @return #ECGEOF when the iterator finishes getting the list of tasks.
 */
int cgroup_get_task_next(void **handle, pid_t *pid);

/**
 * Release the iterator.
 */
int cgroup_get_task_end(void **handle);

/**
 * @}
 *
 * @name List mounted controllers
 * Use following function to list mounted controllers and to see, how they
 * are mounted together in hierarchies.
 * Use cgroup_get_all_controller_begin() (see later) to list all controllers,
 * including those which are not mounted.
 * @{
 */

/**
 * Information about mounted controller.
 */
struct cgroup_mount_point {
	/** Name of the controller. */
	char name[FILENAME_MAX];
	/** Mount point of the controller. */
	char path[FILENAME_MAX];
};

/**
 * Read the mount table to give a list where each controller is
 * mounted.
 * @param handle The handle to be used for iteration.
 * @param info The variable where the path to the controller is stored.
 * @return #ECGEOF when no controllers are mounted.
 */
int cgroup_get_controller_begin(void **handle, struct cgroup_mount_point *info);

/**
 * Read the next mounted controller.
 * While walking through the mount table, the controllers are
 * returned in order of their mount points, i.e. controllers mounted together
 * in one hierarchy are returned next to each other.
 * @param handle The handle to be used for iteration.
 * @param info The variable where the path to the controller is stored.
 * @return #ECGEOF when all controllers were already returned.
 */
int cgroup_get_controller_next(void **handle, struct cgroup_mount_point *info);

/**
 * Release the iterator.
 */
int cgroup_get_controller_end(void **handle);

/**
 * @}
 *
 * @name List all controllers
 * Use following functions to list all controllers, including those which are
 * not mounted. The controllers are returned in the same order as in
 * /proc/cgroups file, i.e. mostly random.
 */

/**
 * Detailed information about available controller.
 */
struct controller_data {
	/** Controller name. */
	char name[FILENAME_MAX];
	/**
	 * Hierarchy ID. Controllers with the same hierarchy ID
	 * are mounted together as one hierarchy. Controllers with
	 * ID 0 are not currently mounted anywhere.
	 */
	int hierarchy;
	/** Number of groups. */
	int num_cgroups;
	/** Enabled flag. */
	int enabled;
};

/**
 * Read the first of controllers from /proc/cgroups.
 * @param handle The handle to be used for iteration.
 * @param info The structure which will be filled with controller data.
 */
int cgroup_get_all_controller_begin(void **handle,
	struct controller_data *info);
/**
 * Read next controllers from /proc/cgroups.
 * @param handle The handle to be used for iteration.
 * @param info The structure which will be filled with controller data.
 */
int cgroup_get_all_controller_next(void **handle, struct controller_data *info);

/**
 * Release the iterator
 */
int cgroup_get_all_controller_end(void **handle);

/**
 * @}
 *
 * @name List all mount points of a controller.
 * Use following functions to list all mount points of a hierarchy with given
 * controller.
 */

/**
 * Read the first mount point of the hierarchy with given controller.
 * The first is the same as the mount point returned by
 * cgroup_get_subsys_mount_point().
 * @param handle The handle to be used for iteration.
 * @param controller The controller name.
 * @param path Buffer to fill the path into. The buffer must be at least
 * FILENAME_MAX characters long.
 */
int cgroup_get_subsys_mount_point_begin(const char *controller, void **handle,
					char *path);

/**
 * Read next mount point of the hierarchy with given controller.
 * @param handle The handle to be used for iteration.
 * @param path Buffer to fill the path into. The buffer must be at least
 * FILENAME_MAX characters long.
 */
int cgroup_get_subsys_mount_point_next(void **handle, char *path);

/**
 * Release the iterator.
 */
int cgroup_get_subsys_mount_point_end(void **handle);

/**
 * @}
 * @}
 */

#ifdef __cplusplus
} /* extern "C" */
#endif

#endif /* _LIBCGROUP_ITERATORS_H */
