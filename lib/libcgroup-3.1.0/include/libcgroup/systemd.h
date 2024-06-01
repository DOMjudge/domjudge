/* SPDX-License-Identifier: LGPL-2.1-only */
#ifndef _LIBCGROUP_SYSTEMD_H
#define _LIBCGROUP_SYSTEMD_H

#ifndef _LIBCGROUP_H_INSIDE
#error "Only <libcgroup.h> should be included directly."
#endif

#ifdef __cplusplus
extern "C" {
#endif

enum cgroup_systemd_mode_t {
	CGROUP_SYSTEMD_MODE_FAIL = 0,
	CGROUP_SYSTEMD_MODE_REPLACE,
	CGROUP_SYSTEMD_MODE_ISOLATE,
	CGROUP_SYSTEMD_MODE_IGNORE_DEPS,
	CGROUP_SYSTEMD_MODE_IGNORE_REQS,

	CGROUP_SYSTEMD_MODE_CNT,
	CGROUP_SYSTEMD_MODE_DFLT = CGROUP_SYSTEMD_MODE_REPLACE
};

/**
 * Options associated with creating a systemd scope
 */
struct cgroup_systemd_scope_opts {
	/** should systemd delegate this cgroup or not.  1 == yes, 0 == no */
	int delegated;
	/** systemd behavior when the scope already exists */
	enum cgroup_systemd_mode_t mode;
	/** pid to be placed in the cgroup.  if 0, libcgroup will create a dummy process */
	pid_t pid;
};

/*
 * cgroup systemd settings
 */
struct cgroup_systemd_opts {
	char	slice_name[FILENAME_MAX];
	char	scope_name[FILENAME_MAX];
	int	setdefault;
	pid_t	pid;
	struct cgroup_systemd_opts *next;
};

/**
 * Populate the scope options structure with default values
 *
 * @param opts Scope creation options structure instance.  Must already be allocated
 *
 * @return 0 on success and > 0 on error
 */
int cgroup_set_default_scope_opts(struct cgroup_systemd_scope_opts * const opts);

/**
 * Create a systemd scope under the specified slice
 *
 * @param scope_name Name of the scope, must end in .scope
 * @param slice_name Name of the slice, must end in .slice
 * @param opts Scope creation options structure instance
 *
 * @return 0 on success and > 0 on error
 */
int cgroup_create_scope(const char * const scope_name, const char * const slice_name,
			const struct cgroup_systemd_scope_opts * const opts);

/**
 * Create a systemd scope
 *
 * @param cgroup
 * @param ignore_ownership When nonzero, all errors are ignored when setting owner of the group
 *	owner of the group and/or its tasks file
 * @param opts Scope creation options structure instance
 *
 * @return 0 on success and > 0 on error
 *
 * @note The cgroup->name field should be of the form "foo.slice/bar.scope"
 */
int cgroup_create_scope2(struct cgroup *cgroup, int ignore_ownership,
			 const struct cgroup_systemd_scope_opts * const opts);

/**
 * Parse the systemd default cgroup's relative path from
 * /var/run/libcgroup/systemd and set it as default delegation cgroup
 * path, if available.
 *
 * The path is relative to cgroup root (default: /sys/fs/cgroup)
 *
 * @return 1 if a valid default slice/scope is set, 0 in all other cases
 */
int cgroup_set_default_systemd_cgroup(void);

/**
 * Parse the systemd delegation settings from the configuration file
 * and allocate a new cgroup_systemd_opts object.
 * This function internally calls cgroup_add_systemd_opts() to add the conf and
 * value to the newly allocated cgroup_systemd_opts object.
 *
 * @param conf Name of the systemd delegate setting read from configuration file.
 * @param value The value of the conf systemd delegate setting.
 *
 * @return 1 on success and 0 on error
 */
int cgroup_alloc_systemd_opts(const char * const conf, const char * const value);

/**
 * Parse the systemd delegation settings from the configuration file
 * and add the conf and value to the last allocated cgroup_systemd_opts object
 * (tail) allocated by cgroup_alloc_systemd_opts()
 *
 * @param conf Name of the systemd delegate setting read from configuration file.
 * @param value The value of the conf systemd delegate setting.
 *
 * @return 1 on success and 0 on error
 */
int cgroup_add_systemd_opts(const char * const conf, const char * const value);

/**
 * Free the cgroup_systemd_opts objects allocated by cgroup_alloc_systemd_opts()
 */
void cgroup_cleanup_systemd_opts(void);

/*
 * Write the specified slice and scope to the libcgroup systemd run file. This
 * slice and scope will then be used as the default cgroup root. Subsequent
 * libcgroup commands, cgget, etc., will utilize this slice and scope when
 * constructing the libcgroup path
 *
 * @param slice Slice name, e.g. libcgroup.slice
 * @param scope Scope name, e.g. database.scope
 */
int cgroup_write_systemd_default_cgroup(const char * const slice,
				        const char * const scope);

#ifdef __cplusplus
} /* extern "C" */
#endif

#endif /* _LIBCGROUP_SYSTEMD_H */
