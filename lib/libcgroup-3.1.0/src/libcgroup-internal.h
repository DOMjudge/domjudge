/* SPDX-License-Identifier: LGPL-2.1-only */
/**
 * Copyright IBM Corporation. 2008
 *
 * Author:	Dhaval Giani <dhaval@linux.vnet.ibm.com>
 */

#ifndef __LIBCG_INTERNAL

#define __LIBCG_INTERNAL

#ifdef __cplusplus
extern "C" {
#endif

#include "config.h"

#include <libcgroup.h>

#include <pthread.h>
#include <dirent.h>
#include <limits.h>
#include <mntent.h>
#include <setjmp.h>
#include <fts.h>

#include <sys/stat.h>
#include <sys/types.h>

#define MAX_MNT_ELEMENTS	16	/* Maximum number of mount points/controllers */
#define MAX_GROUP_ELEMENTS	128	/* Estimated number of groups created */

#define CG_CONTROL_VALUE_MAX	4096	/* Maximum length of a value */

#define CG_NV_MAX		100
#define CG_CONTROLLER_MAX	100
#define CG_OPTIONS_MAX		100

/*
 * Max number of mounted hierarchies. Event if one controller is mounted
 * per hier, it can not exceed CG_CONTROLLER_MAX
 */
#define CG_HIER_MAX  CG_CONTROLLER_MAX

#define CONTROL_NAMELEN_MAX	32	/* Maximum length of a controller's name */

/* Definitions for the uid and gid members of a cgroup_rules */
#define CGRULE_INVALID	((uid_t) -1)
#define CGRULE_WILD	((uid_t) -2)

#define CGRULE_SUCCESS_STORE_PID	"SUCCESS_STORE_PID"
#define CGRULE_OPTION_IGNORE		"ignore" /* Definitions for the cgrules options field */

#define CGCONFIG_CONF_FILE		"/etc/cgconfig.conf"
/* Minimum number of file in template file list for cgrulesengd */
#define CGCONFIG_CONF_FILES_LIST_MINIMUM_SIZE   4
#define CGCONFIG_CONF_DIR		"/etc/cgconfig.d"

#define CGRULES_CONF_FILE		"/etc/cgrules.conf"
#define CGRULES_CONF_DIR		"/etc/cgrules.d"
#define CGRULES_MAX_FIELDS_PER_LINE	3

#define CGROUP_BUFFER_LEN	(5 * FILENAME_MAX)

/* Maximum length of a key(<user>:<process name>) in the daemon config file */
#define CGROUP_RULE_MAXKEY	(LOGIN_NAME_MAX + FILENAME_MAX + 1)

/* Maximum length of a line in the daemon config file */
#define CGROUP_RULE_MAXLINE	(FILENAME_MAX + CGROUP_RULE_MAXKEY + CG_CONTROLLER_MAX + 3)

#define CGROUP_FILE_PREFIX	"cgroup"

/* cgroup v2 files */
#define CGV2_CONTROLLERS_FILE   "cgroup.controllers"
#define CGV2_SUBTREE_CTRL_FILE  "cgroup.subtree_control"

/* maximum line length when reading the cgroup.controllers file */
#define CGV2_CONTROLLERS_LL_MAX	100

#define cgroup_err(x...)	cgroup_log(CGROUP_LOG_ERROR, "Error: " x)
#define cgroup_warn(x...)	cgroup_log(CGROUP_LOG_WARNING, "Warning: " x)
#define cgroup_info(x...)	cgroup_log(CGROUP_LOG_INFO, "Info: " x)
#define cgroup_dbg(x...)	cgroup_log(CGROUP_LOG_DEBUG, x)
#define cgroup_cont(x...)	cgroup_log(CGROUP_LOG_CONT, x)

#define CGROUP_DEFAULT_LOGLEVEL CGROUP_LOG_ERROR

#define max(x, y) ((y) < (x)?(x):(y))
#define min(x, y) ((y) > (x)?(x):(y))

struct control_value {
	char name[FILENAME_MAX];
	char value[CG_CONTROL_VALUE_MAX];

	/* cgget uses this field for values that span multiple lines */
	char *multiline_value;

	/*
	 * The abstraction layer uses prev_name when there's an
	 * N->1 or 1->N relationship between cgroup v1 and v2 settings.
	 */
	char *prev_name;

	bool dirty;
};

struct cgroup_controller {
	char name[CONTROL_NAMELEN_MAX];
	struct control_value *values[CG_NV_MAX];
	struct cgroup *cgroup;
	int index;
	enum cg_version_t version;
};

struct cgroup {
	char name[FILENAME_MAX];
	struct cgroup_controller *controller[CG_CONTROLLER_MAX];
	int index;
	uid_t tasks_uid;
	gid_t tasks_gid;
	mode_t task_fperm;
	uid_t control_uid;
	gid_t control_gid;
	mode_t control_fperm;
	mode_t control_dperm;
};

struct cg_mount_point {
	char path[FILENAME_MAX];
	struct cg_mount_point *next;
};

struct cg_mount_table_s {
	/** Controller name. */
	char name[CONTROL_NAMELEN_MAX];
	/**
	 * List of mount points, at least one mount point is there for sure.
	 */
	struct cg_mount_point mount;
	int index;
	int shared_mnt;
	enum cg_version_t version;
};

struct cgroup_rules_data {
	pid_t pid; /* pid of the process which needs to change group */

	/* Details of user under consideration for destination cgroup */
	struct passwd *pw;
	/* Gid of the process */
	gid_t gid;
};

/* A rule that maps UID/GID to a cgroup */
struct cgroup_rule {
	uid_t uid;
	gid_t gid;
	bool is_ignore;
	char *procname;
	char username[LOGIN_NAME_MAX];
	char destination[FILENAME_MAX];
	char *controllers[MAX_MNT_ELEMENTS];
	struct cgroup_rule *next;
};

/* Container for a list of rules */
struct cgroup_rule_list {
	struct cgroup_rule *head;
	struct cgroup_rule *tail;
	int len;
};

/* The walk_tree handle */
struct cgroup_tree_handle {
	FTS *fts;
	int flags;
};

/**
 * Internal item of dictionary. Linked list is sufficient for now - we need
 * only 'add' operation and simple iterator. In future, this might be easily
 * rewritten to dynamic array when random access is needed, just keep in mind
 * that the order is important and the iterator should return the items in
 * the order they were added there.
 */
struct cgroup_dictionary_item {
	const char *name;
	const char *value;
	struct cgroup_dictionary_item *next;
};

/* Flags for cgroup_dictionary_create */
/**
 * All items (i.e. both name and value strings) stored in the dictionary
 * should *NOT* be free()d on cgroup_dictionary_free(), only the  dictionary
 * helper structures (i.e. underlying linked list) should be freed.
 */
#define CG_DICT_DONT_FREE_ITEMS	1

/**
 * Dictionary of (name, value) items.
 * The dictionary keeps its order, iterator iterates in the same order as
 * the items were added there. It is *not* hash-style structure, it does
 * not provide random access to its items nor quick search. This structure
 * should be opaque to users of the dictionary, underlying data structure
 * might change anytime and without warnings.
 */
struct cgroup_dictionary {
	struct cgroup_dictionary_item *head;
	struct cgroup_dictionary_item *tail;
	int flags;
};

/** Opaque iterator of an dictionary. */
struct cgroup_dictionary_iterator {
	struct cgroup_dictionary_item *item;
};

/**
 * per thread errno variable, to be used when return code is ECGOTHER
 */
extern __thread int last_errno;

/**
 * 'Exception handler' for lex parser.
 */
extern jmp_buf parser_error_env;

/* Internal API */
char *cg_build_path(const char *name, char *path, const char *type);
int cgroup_get_uid_gid_from_procfs(pid_t pid, uid_t *euid, gid_t *egid);
int cgroup_get_procname_from_procfs(pid_t pid, char **procname);
int cg_mkdir_p(const char *path);
struct cgroup *create_cgroup_from_name_value_pairs(const char *name,
						struct control_value *name_value, int nv_number);
void init_cgroup_table(struct cgroup *cgroups, size_t count);

/*
 * Main mounting structures
 *
 * cg_mount_table_lock must be held to access:
 * 	cg_mount_table
 * 	cg_cgroup_v2_mount_path
 */
extern struct cg_mount_table_s cg_mount_table[CG_CONTROLLER_MAX];
extern char cg_cgroup_v2_mount_path[FILENAME_MAX];
extern pthread_rwlock_t cg_mount_table_lock;

/*
 * config related structures
 */
extern __thread char *cg_namespace_table[CG_CONTROLLER_MAX];

/*
 * Default systemd cgroup used by the cg_build_path_locked() and tools
 * setting the default cgroup path.
 */
extern char systemd_default_cgroup[FILENAME_MAX * 2 + 1];

/*
 * config related API
 */
int cgroup_config_insert_cgroup(char *cg_name);
int cgroup_config_parse_controller_options(char *controller, struct cgroup_dictionary *values);
int template_config_insert_cgroup(char *cg_name);
int template_config_parse_controller_options(char *controller, struct cgroup_dictionary *values);
int template_config_group_task_perm(char *perm_type, char *value);
int template_config_group_admin_perm(char *perm_type, char *value);
int cgroup_config_group_task_perm(char *perm_type, char *value);
int cgroup_config_group_admin_perm(char *perm_type, char *value);
int cgroup_config_insert_into_mount_table(char *name, char *mount_point);
int cgroup_config_insert_into_namespace_table(char *name, char *mount_point);
void cgroup_config_cleanup_mount_table(void);
void cgroup_config_cleanup_namespace_table(void);
int cgroup_config_define_default(void);

/**
 * Create an empty dictionary.
 */
extern int cgroup_dictionary_create(struct cgroup_dictionary **dict, int flags);

/**
 * Add an item to existing dictionary.
 */
extern int cgroup_dictionary_add(struct cgroup_dictionary *dict, const char *name,
				 const char *value);
/**
 * Fully destroy existing dictionary. Depending on flags passed to
 * cgroup_dictionary_create(), names and values might get destroyed too.
 */
extern int cgroup_dictionary_free(struct cgroup_dictionary *dict);

/**
 * Start iterating through a dictionary. The items are returned in the same
 * order as they were added using cgroup_dictionary_add().
 */
extern int cgroup_dictionary_iterator_begin(struct cgroup_dictionary *dict, void **handle,
					    const char **name, const char **value);
/**
 * Continue iterating through the dictionary.
 */
extern int cgroup_dictionary_iterator_next(void **handle, const char **name, const char **value);

/**
 * Finish iteration through the dictionary.
 */
extern void cgroup_dictionary_iterator_end(void **handle);

/**
 * Changes permissions for given path. If owner_is_umask is specified then
 * it uses owner permissions as a mask for group and others permissions.
 *
 * @param path Patch to chmod.
 * @param mode File permissions to set.
 * @param owner_is_umask Flag whether path owner permissions should be used
 *	as a mask for group and
 * others permissions.
 */
int cg_chmod_path(const char *path, mode_t mode, int owner_is_umask);

/**
 * Build the path to the tasks or cgroup.procs file
 *
 * @param path Output variable that will contain the path.  Must be of size
 *	FILENAME_MAX or larger
 * @param path_sz Size of the path string
 * @param cg_name Cgroup name
 * @param ctrl_name Controller name
 */
int cgroup_build_tasks_procs_path(char * const path, size_t path_sz, const char * const cg_name,
				  const char * const ctrl_name);

/**
 * Build the full path to the controller/setting
 *
 * @param setting Cgroup virtual filename/setting (optional)
 * @param path Output variable to contain the concatenated path
 * @param controller Cgroup controller name
 *
 * @return If successful, a valid pointer to the concatenated path
 *
 * @note The cg_mount_table_lock must be held prior to calling this function
 */
char *cg_build_path_locked(const char *setting, char *path, const char *controller);

/**
 * Given a cgroup controller and a setting within it, populate the setting's value
 *
 * @param ctrl_dir dirent representation of the setting, e.g. memory.stat
 * @param cgroup current cgroup
 * @param cgc current cgroup controller
 * @param cg_index Index into the cg_mount_table of the cgroup
 *
 * @note The cg_mount_table_lock must be held prior to calling this function
 */
int cgroup_fill_cgc(struct dirent *ctrl_dir, struct cgroup *cgroup, struct cgroup_controller *cgc,
		    int cg_index);

/**
 * Given a controller name, test if it's mounted
 *
 * @param ctrl_name Controller name
 * @return 1 if mounted, 0 if not mounted
 */
int cgroup_test_subsys_mounted(const char *ctrl_name);

/**
 * Create a duplicate copy of values under the specified controller
 *
 * @dst: Destination controller
 * @src: Source controller from which values will be copied to dst
 *
 * @return 0 on a successful copy, ECGOTHER if the copy failed
 */
int cgroup_copy_controller_values(struct cgroup_controller * const dst,
				  const struct cgroup_controller * const src);

/**
 * Remove a name/value pair from a controller.
 *
 * @param controller
 * @param name The name of the name/value pair to be removed
 * @return 0 on success.  ECGROUPNOTEXIST if name does not exist.
 */
int cgroup_remove_value(struct cgroup_controller * const controller, const char * const name);

/**
 * Free the specified controller from the group.
 * @param ctrl
 *
 * Note it's up to the caller to decrement the cgroup's index
 */
void cgroup_free_controller(struct cgroup_controller *ctrl);

/**
 * Functions that are defined as STATIC can be placed within the UNIT_TEST
 * ifdef.  This will allow them to be included in the unit tests while
 * remaining static in a normal libcgroup library build.
 */
#ifdef UNIT_TEST

#define TEST_PROC_PID_CGROUP_FILE "test-procpidcgroup"

int cgroup_parse_rules_options(char *options, struct cgroup_rule * const rule);
int cg_get_cgroups_from_proc_cgroups(pid_t pid, char *cgroup_list[], char *controller_list[],
				     int list_len);
bool cgroup_compare_ignore_rule(const struct cgroup_rule * const rule, pid_t pid,
				const char * const procname);
bool cgroup_compare_wildcard_procname(const char * const rule_procname,
				      const char * const procname);
int cgroup_process_v1_mnt(char *controllers[], struct mntent *ent, int *mnt_tbl_idx);
int cgroup_process_v2_mnt(struct mntent *ent, int *mnt_tbl_idx);
int cgroup_set_values_recursive(const char * const base,
				const struct cgroup_controller * const controller,
				bool ignore_non_dirty_failures);
int cgroup_chown_chmod_tasks(const char * const cg_path, uid_t uid, gid_t gid, mode_t fperm);
int cgroupv2_subtree_control(const char *path, const char *ctrl_name, bool enable);
int cgroupv2_get_subtree_control(const char *path,  const char *ctrl_name, bool * const enabled);
int cgroupv2_controller_enabled(const char * const cg_name, const char * const ctrl_name);

#endif /* UNIT_TEST */

#ifdef __cplusplus
} /* extern "C" */
#endif

#endif
