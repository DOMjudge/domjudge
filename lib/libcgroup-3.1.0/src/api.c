// SPDX-License-Identifier: LGPL-2.1-only
/**
 * Copyright IBM Corporation. 2007
 *
 * Author:	Dhaval Giani <dhaval@linux.vnet.ibm.com>
 * Author:	Balbir Singh <balbir@linux.vnet.ibm.com>
 *
 * TODOs:
 *	1. Add more APIs for the control groups.
 *	2. Handle the configuration related APIs.
 *
 * Code initiated and designed by Dhaval Giani. All faults are most likely
 * his mistake.
 *
 * Bharata B Rao <bharata@linux.vnet.ibm.com> is willing is take blame
 * for mistakes in APIs for reading statistics.
 */

#ifndef _GNU_SOURCE
#define _GNU_SOURCE
#endif

#include <libcgroup.h>
#include <libcgroup-internal.h>

#include <pthread.h>
#include <dirent.h>
#include <unistd.h>
#include <mntent.h>
#include <stdlib.h>
#include <string.h>
#include <libgen.h>
#include <assert.h>
#include <errno.h>
#include <stdio.h>
#include <fcntl.h>
#include <ctype.h>
#include <fts.h>
#include <pwd.h>
#include <grp.h>

#include <sys/syscall.h>
#include <sys/socket.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <sys/vfs.h>

#include <linux/un.h>

const struct cgroup_library_version library_version = {
	.major = CGROUP_VER_MAJOR,
	.minor = CGROUP_VER_MINOR,
	.release = CGROUP_VER_RELEASE,
};

/*
 * The errno which happend the last time (have to be thread specific)
 */
__thread int last_errno;

#define MAXLEN 256

/* the value have to be thread specific */
static __thread char errtext[MAXLEN];

/* Task command name length */
#define TASK_COMM_LEN 16

/* Check if cgroup_init has been called or not. */
static int cgroup_initialized;

/* List of configuration rules */
static struct cgroup_rule_list rl;

/* Temporary list of configuration rules (for non-cache apps) */
static struct cgroup_rule_list trl;

/* Lock for the list of rules (rl) */
static pthread_rwlock_t rl_lock = PTHREAD_RWLOCK_INITIALIZER;

/* Cgroup v2 mount path.  Null if v2 isn't mounted */
char cg_cgroup_v2_mount_path[FILENAME_MAX];

/* Namespace */
__thread char *cg_namespace_table[CG_CONTROLLER_MAX];

pthread_rwlock_t cg_mount_table_lock = PTHREAD_RWLOCK_INITIALIZER;
struct cg_mount_table_s cg_mount_table[CG_CONTROLLER_MAX];

/* Cgroup v2 mount paths, with empty controllers */
struct cg_mount_point *cg_cgroup_v2_empty_mount_paths;

#ifdef WITH_SYSTEMD
/* Default systemd path name. Length: <name>.slice/<name>.scope */
char systemd_default_cgroup[FILENAME_MAX * 2 + 1];
#endif

const char * const cgroup_strerror_codes[] = {
	"Cgroup is not compiled in",
	"Cgroup is not mounted",
	"Cgroup does not exist",
	"Cgroup has not been created",
	"Cgroup one of the needed subsystems is not mounted",
	"Cgroup, request came in from non owner",
	"Cgroup controllers are bound to different mount points",
	"Cgroup, operation not allowed",
	"Cgroup value set exceeds maximum",
	"Cgroup controller already exists",
	"Cgroup value already exists",
	"Cgroup invalid operation",
	"Cgroup, creation of controller failed",
	"Cgroup operation failed",
	"Cgroup not initialized",
	"Cgroup, requested group parameter does not exist",
	"Cgroup generic error",
	"Cgroup values are not equal",
	"Cgroup controllers are different",
	"Cgroup parsing failed",
	"Cgroup, rules file does not exist",
	"Cgroup mounting failed",
	"",				/* 50022 is reserved for future errors */
	"End of File or iterator",
	"Failed to parse config file",
	"Have multiple paths for the same namespace",
	"Controller in namespace does not exist",
	"Either mount or namespace keyword has to be specified in the configuration file",
	"This kernel does not support this feature",
	"Value setting does not succeed",
	"Failed to remove a non-empty group",
	"Failed to convert from cgroup v1 to/from cgroup v2",
};

static const char * const cgroup_ignored_tasks_files[] = { "tasks", NULL };

#ifndef UNIT_TEST
static int cg_get_cgroups_from_proc_cgroups(pid_t pid, char *cgroup_list[],
					    char *controller_list[], int list_len);

static int cgroupv2_get_subtree_control(const char *path, const char *ctrl_name,
					bool * const enabled);
#endif

static int cg_chown(const char *filename, uid_t owner, gid_t group)
{
	if (owner == NO_UID_GID)
		owner = getuid();
	if (group == NO_UID_GID)
		group = getgid();

	return chown(filename, owner, group);
}
static int cg_chown_file(FTS *fts, FTSENT *ent, uid_t owner, gid_t group)
{
	const char *filename = fts->fts_path;
	int ret = 0;

	cgroup_dbg("chown: seeing file %s\n", filename);
	switch (ent->fts_info) {
	case FTS_ERR:
		errno = ent->fts_errno;
		break;
	case FTS_D:
	case FTS_DC:
	case FTS_NSOK:
	case FTS_NS:
	case FTS_DNR:
	case FTS_DP:
	case FTS_F:
	case FTS_DEFAULT:
		ret = cg_chown(filename, owner, group);
		break;
	}
	if (ret < 0) {
		cgroup_warn("cannot change owner of file %s: %s\n", filename, strerror(errno));
		last_errno = errno;
		ret = ECGOTHER;
	}
	return ret;
}

/* TODO: Need to decide a better place to put this function. */
static int cg_chown_recursive(char **path, uid_t owner, gid_t group)
{
	int ret = 0;
	FTS *fts;

	cgroup_dbg("chown: path is %s\n", *path);
	fts = fts_open(path, FTS_PHYSICAL | FTS_NOCHDIR | FTS_NOSTAT, NULL);
	if (fts == NULL) {
		cgroup_warn("cannot open directory %s: %s\n", path, strerror(errno));
		last_errno = errno;
		return ECGOTHER;
	}

	while (1) {
		FTSENT *ent;

		ent = fts_read(fts);
		if (!ent) {
			cgroup_warn("fts_read failed\n");
			break;
		}
		ret = cg_chown_file(fts, ent, owner, group);
	}
	fts_close(fts);

	return ret;
}

int cg_chmod_path(const char *path, mode_t mode, int owner_is_umask)
{
	mode_t mask = -1U;
	struct stat buf;
	int fd;

	fd = open(path, O_RDONLY);
	if (fd == -1)
		goto fail;

	if (owner_is_umask) {
		mode_t umask, gmask, omask;
		/*
		 * Use owner permissions as an umask for group and others
		 * permissions because we trust kernel to initialize owner
		 * permissions to something useful.  Keep SUID and SGID bits.
		 */
		if (fstat(fd, &buf) == -1)
			goto fail;

		/* 0700 == S_IRWXU */
		umask = 0700 & buf.st_mode;
		gmask = umask >> 3;
		omask = gmask >> 3;

		mask = umask|gmask|omask|S_ISUID|S_ISGID|S_ISVTX;
	}

	if (fchmod(fd, mode & mask))
		goto fail;

	close(fd);

	return 0;

fail:
	cgroup_warn("cannot change permissions of file %s: %s\n", path, strerror(errno));
	last_errno = errno;

	if (fd > -1)
		close(fd);

	return ECGOTHER;
}

int cg_chmod_file(FTS *fts, FTSENT *ent, mode_t dir_mode, int dirm_change, mode_t file_mode,
		  int filem_change, int owner_is_umask)
{
	const char *filename = fts->fts_path;
	int ret = 0;

	cgroup_dbg("chmod: seeing file %s\n", filename);

	switch (ent->fts_info) {
	case FTS_ERR:
		errno = ent->fts_errno;
		break;
	case FTS_D:
	case FTS_DC:
	case FTS_DNR:
	case FTS_DP:
		if (dirm_change)
			ret = cg_chmod_path(filename, dir_mode, owner_is_umask);
		break;
	case FTS_F:
	case FTS_NSOK:
	case FTS_NS:
	case FTS_DEFAULT:
		if (filem_change)
			ret = cg_chmod_path(filename, file_mode, owner_is_umask);
		break;
	}

	return ret;
}

/**
 * Changes permissions of all directories and control files (i.e. all files
 * except files named in ignore_list. The list must be terminated with NULL.
 */
static int cg_chmod_recursive_controller(char *path, mode_t dir_mode, int dirm_change,
					 mode_t file_mode, int filem_change, int owner_is_umask,
					 const char * const *ignore_list)
{
	int final_ret = 0;
	char *fts_path[2];
	int i, ignored;
	int ret = 0;
	FTS *fts;

	fts_path[0] = path;
	fts_path[1] = NULL;
	cgroup_dbg("chmod: path is %s\n", path);

	fts = fts_open(fts_path, FTS_PHYSICAL | FTS_NOCHDIR | FTS_NOSTAT, NULL);
	if (fts == NULL) {
		cgroup_warn("cannot open directory %s: %s\n", fts_path, strerror(errno));
		last_errno = errno;
		return ECGOTHER;
	}

	while (1) {
		FTSENT *ent;

		ent = fts_read(fts);
		if (!ent) {
			if (errno != 0) {
				cgroup_dbg("fts_read failed\n");
				last_errno = errno;
				final_ret = ECGOTHER;
			}
			break;
		}

		ignored = 0;
		if (ignore_list != NULL)
			for (i = 0; ignore_list[i] != NULL; i++)
				if (!strcmp(ignore_list[i], ent->fts_name)) {
					ignored = 1;
					break;
				}
		if (ignored)
			continue;

		ret = cg_chmod_file(fts, ent, dir_mode, dirm_change, file_mode, filem_change,
				    owner_is_umask);
		if (ret) {
			cgroup_warn("cannot change file mode %s: %s\n", fts_path, strerror(errno));
			last_errno = errno;
			final_ret = ECGOTHER;
		}
	}
	fts_close(fts);

	return final_ret;
}

int cg_chmod_recursive(struct cgroup *cgroup, mode_t dir_mode, int dirm_change, mode_t file_mode,
		       int filem_change)
{
	int final_ret = 0;
	char *path;
	int i, ret;

	path = malloc(FILENAME_MAX);
	if (!path) {
		last_errno = errno;
		return ECGOTHER;
	}
	for (i = 0; i < cgroup->index; i++) {
		if (!cg_build_path(cgroup->name, path, cgroup->controller[i]->name)) {
			final_ret = ECGFAIL;
			break;
		}

		ret = cg_chmod_recursive_controller(path, dir_mode, dirm_change, file_mode,
						    filem_change, 0, NULL);
		if (ret)
			final_ret = ret;
	}
	free(path);

	return final_ret;
}

void cgroup_set_permissions(struct cgroup *cgroup, mode_t control_dperm, mode_t control_fperm,
			    mode_t task_fperm)
{
	if (!cgroup) {
		/* ECGROUPNOTALLOWED */
		cgroup_err("Cgroup, operation not allowed\n");
		return;
	}

	cgroup->control_dperm = control_dperm;
	cgroup->control_fperm = control_fperm;
	cgroup->task_fperm = task_fperm;
}

static char *cgroup_basename(const char *path)
{
	char *tmp_string;
	char *base;

	tmp_string = strdup(path);

	if (!tmp_string)
		return NULL;

	base = strdup(basename(tmp_string));

	free(tmp_string);

	return base;
}

int cgroup_test_subsys_mounted(const char *name)
{
	int i;

	pthread_rwlock_rdlock(&cg_mount_table_lock);

	for (i = 0; cg_mount_table[i].name[0] != '\0'; i++) {
		if (strncmp(cg_mount_table[i].name, name, sizeof(cg_mount_table[i].name)) == 0) {
			pthread_rwlock_unlock(&cg_mount_table_lock);
			return 1;
		}

		/*
		 * The user has likely requested a file like cgroup.type or
		 * cgroup.procs. Allow this request as long as there's a
		 * cgroup v2 controller mounted.
		 */
		if (strncmp(name, CGROUP_FILE_PREFIX, strlen(CGROUP_FILE_PREFIX)) == 0 &&
		    cg_mount_table[i].version == CGROUP_V2) {
			pthread_rwlock_unlock(&cg_mount_table_lock);
			return 1;
		}
	}

	pthread_rwlock_unlock(&cg_mount_table_lock);

	return 0;
}

/**
 * Free a single cgroup_rule struct.
 *	@param r The rule to free from memory
 */
static void cgroup_free_rule(struct cgroup_rule *r)
{
	/* Loop variable */
	int i = 0;

	/* Make sure our rule is not NULL, first. */
	if (!r) {
		cgroup_warn("attempted to free NULL rule\n");
		return;
	}
	if (r->procname) {
		free(r->procname);
		r->procname = NULL;
	}
	/* We must free any used controller strings, too. */
	for (i = 0; i < MAX_MNT_ELEMENTS; i++) {
		if (r->controllers[i])
			free(r->controllers[i]);
	}

	free(r);
}

/**
 * Free a list of cgroup_rule structs.  If rl is the main list of rules, the
 * lock must be taken for writing before calling this function!
 *	@param rl Pointer to the list of rules to free from memory
 */
static void cgroup_free_rule_list(struct cgroup_rule_list *cg_rl)
{
	/* Temporary pointer */
	struct cgroup_rule *tmp = NULL;

	/* Make sure we're not freeing NULL memory! */
	if (!(cg_rl->head)) {
		cgroup_warn("attempted to free NULL list\n");
		return;
	}

	while (cg_rl->head) {
		tmp = cg_rl->head;
		cg_rl->head = tmp->next;
		cgroup_free_rule(tmp);
	}

	/* Don't leave wild pointers around! */
	cg_rl->head = NULL;
	cg_rl->tail = NULL;
}

static char *cg_skip_unused_charactors_in_rule(char *rule)
{
	char *itr;

	/* We ignore anything after a # sign as comments. */
	itr = strchr(rule, '#');
	if (itr)
		*itr = '\0';

	/* We also need to remove the newline character. */
	itr = strchr(rule, '\n');
	if (itr)
		*itr = '\0';

	/* Now, skip any leading tabs and spaces. */
	itr = rule;
	while (itr && isblank(*itr))
		itr++;

	/* If there's nothing left, we can ignore this line. */
	if (!strlen(itr))
		return NULL;

	return itr;
}

/**
 * Parse the options field in the rule from the cgrules configuration file
 *
 *	@param options Comma-separated string of options
 *	@param rule The rule that will contain the parsed options
 *	@return 0 on success, -EINVAL if the options are invalid
 * TODO: Make this function thread safe!
 */
STATIC int cgroup_parse_rules_options(char *options, struct cgroup_rule * const rule)
{
	char *stok_buff = NULL;
	size_t cmp_len;
	int ret = 0;

	stok_buff = strtok(options, ",");
	if (!stok_buff) {
		cgroup_err("failed to parse options: %s\n", options);
		return -EINVAL;
	}

	do {
		cmp_len = min(strlen(stok_buff), strlen(CGRULE_OPTION_IGNORE));
		if (strlen(stok_buff) == strlen(CGRULE_OPTION_IGNORE) &&
		    strncmp(stok_buff, CGRULE_OPTION_IGNORE, cmp_len) == 0) {
			rule->is_ignore = true;
			continue;
		}

		/*
		 * "ignore" is the only currently supported option.
		 * Raise an error if we get here.
		 */
		cgroup_err("Unsupported option: %s\n", stok_buff);
		ret = -EINVAL;
		break;
	} while ((stok_buff = strtok(NULL, ",")));

	return ret;
}
/**
 * Parse the configuration file that maps UID/GIDs to cgroups.  If ever the
 * configuration file is modified, applications should call this function to
 * load the new configuration rules.
 *
 * The function caller is responsible for calling free() on each rule in the
 * list.
 *
 * The cache parameter alters the behavior of this function.  If true, this
 * function will read the entire configuration file and store the results in
 * rl (global rules list).  If false, this function will only parse until it
 * finds a rule matching the given UID or GID.  It will store this rule in
 * trl, as well as any children rules (rules that begin with a %) that it has.
 *
 * This function is NOT thread safe!
 *	@param filename configuration file to parse
 *	@param cache True to cache rules, else false
 *	@param muid If cache is false, the UID to match against
 *	@param mgid If cache is false, the GID to match against
 *	@return 0 on success, -1 if no cache and match found, > 0 on error.
 * TODO: Make this function thread safe!
 *
 */
static int cgroup_parse_rules_file(char *filename, bool cache, uid_t muid, gid_t mgid,
				   const char *mprocname)
{
	/* File descriptor for the configuration file */
	FILE *fp = NULL;

	/* Buffer to store the line we're working on */
	char buff[CGROUP_RULE_MAXLINE] = { '\0' };

	/* Iterator for the line we're working on */
	char *itr = NULL;

	/* Pointer to process name in a line of the configuration file */
	char *procname = NULL;

	/* Pointer to the list that we're using */
	struct cgroup_rule_list *lst = NULL;

	/* Rule to add to the list */
	struct cgroup_rule *newrule = NULL;

	/* Structure to get GID from group name */
	struct group *grp;

	/* Structure to get UID from user name */
	struct passwd *pwd;

	/* Temporary storage for a configuration rule */
	char key[CGROUP_RULE_MAXKEY] = { '\0' };
	char user[LOGIN_NAME_MAX] = { '\0' };
	char controllers[CG_CONTROLLER_MAX] = { '\0' };
	char destination[FILENAME_MAX] = { '\0' };
	char options[CG_OPTIONS_MAX] = { '\0' };
	uid_t uid = CGRULE_INVALID;
	gid_t gid = CGRULE_INVALID;
	bool has_options = false;
	size_t len_username;
	int len_procname;

	/* The current line number */
	unsigned int linenum = 0;

	/* Did we skip the previous line? */
	bool skipped = false;

	/* Have we found a matching rule (non-cache mode)? */
	bool matched = false;

	/* Return codes */
	int ret = 0;

	/* Temporary buffer for strtok() */
	char *stok_buff = NULL;

	/* Loop variable. */
	int i = 0;

	/* Determine which list we're using. */
	if (cache)
		lst = &rl;
	else
		lst = &trl;

	/* Open the configuration file. */
	fp = fopen(filename, "re");
	if (!fp) {
		cgroup_warn("failed to open configuration file %s: %s\n", filename,
			    strerror(errno));

		/* originally ret = 0, but this is parse fail, not success */
		ret = ECGRULESPARSEFAIL;
		goto finish;
	}

	/* Now, parse the configuration file one line at a time. */
	cgroup_dbg("Parsing configuration file %s.\n", filename);
	while (fgets(buff, sizeof(buff), fp) != NULL) {
		linenum++;

		itr = cg_skip_unused_charactors_in_rule(buff);
		if (!itr)
			continue;

		/*
		 * If we skipped the last rule and this rule is a continuation
		 * of it (begins with %), then we should skip this rule too.
		 */
		if (skipped && *itr == '%') {
			cgroup_warn("skipped child of invalid rule, line %d.\n", linenum);
			continue;
		}

		/* clear the buffer. */
		grp = NULL;
		pwd = NULL;

		/*
		 * If there is something left, it should be a rule.  Otherwise,
		 * there's an error in the configuration file.
		 */
		skipped = false;
		i = sscanf(itr, "%s%s%s%s", key, controllers, destination, options);
		if (i < 3) {
			cgroup_err("failed to parse configuration file on line %d\n", linenum);
			goto parsefail;
		} else if (i == 3) {
			has_options = false;
		} else if (i == 4) {
			has_options = true;
		}

		procname = strchr(key, ':');
		if (procname) {
			/* <user>:<procname>  <subsystem>  <destination> */
			procname++;	/* skip ':' */
			len_username = procname - key - 1;
			len_procname = strlen(procname);
			if (len_procname < 0) {
				cgroup_err("failed to parse configuration file on line %d\n",
					   linenum);
				goto parsefail;
			}
		} else {
			len_username = strlen(key);
			len_procname = 0;
		}
		len_username = min(len_username, sizeof(user) - 1);
		memset(user, '\0', sizeof(user));
		strncpy(user, key, len_username);
		user[sizeof(user) - 1] = '\0';

		/*
		 * Next, check the user/group.  If it's a % sign, then we are
		 * continuing another rule and UID/GID should not be reset.
		 * If it's a @, we're dealing with a GID rule.  If it's a *,
		 * then we do not need to do a lookup because the rule always
		 * applies (it's a wildcard).  If we're using non-cache mode
		 * and we've found a matching rule, we only continue to parse
		 * if we're looking at a child rule.
		 */
		if ((!cache) && matched && (strncmp(user, "%", 1) != 0)) {
			/* If we make it here, we finished (non-cache). */
			cgroup_dbg("Parsing of configuration file complete.\n\n");
			ret = -1;
			goto close;
		}
		if (strncmp(user, "@", 1) == 0) {
			/* New GID rule. */
			itr = &(user[1]);
			grp = getgrnam(itr);
			if (grp) {
				uid = CGRULE_INVALID;
				gid = grp->gr_gid;
			} else {
				cgroup_warn("Entry for %s not found. Skipping rule on line %d.\n",
					    itr, linenum);
				skipped = true;
				continue;
			}
		} else if (strncmp(user, "*", 1) == 0) {
			/* Special wildcard rule. */
			uid = CGRULE_WILD;
			gid = CGRULE_WILD;
		} else if (*itr != '%') {
			/* New UID rule. */
			pwd = getpwnam(user);
			if (pwd) {
				uid = pwd->pw_uid;
				gid = CGRULE_INVALID;
			} else {
				cgroup_warn("Entry for %s not found. Skipping rule on line %d.\n",
					    user, linenum);
				skipped = true;
				continue;
			}
		} /* Else, we're continuing another rule (UID/GID are okay). */

		/*
		 * If we are not caching rules, then we need to check for a
		 * match before doing anything else.  We consider four cases:
		 * 1. The UID matches
		 * 2. The GID matches
		 * 3. The UID is a member of the GID, or
		 * 4. We're looking at the wildcard rule, which always matches.
		 * If none of these are true, we simply continue to the next
		 * line in the file.
		 */
		if (grp && muid != CGRULE_INVALID) {
			pwd = getpwuid(muid);
			if (!pwd)
				continue;

			for (i = 0; grp->gr_mem[i]; i++) {
				if (!(strcmp(pwd->pw_name, grp->gr_mem[i])))
					matched = true;
			}
		}

		if (uid == muid || gid == mgid || uid == CGRULE_WILD)
			matched = true;

		if (!cache) {
			if (!matched)
				continue;
			if (len_procname) {
				char *mproc_base;
				/*
				 * If there is a rule based on process name,
				 * it should be matched with mprocname.
				 */
				if (!mprocname) {
					uid = CGRULE_INVALID;
					gid = CGRULE_INVALID;
					matched = false;
					continue;
				}

				mproc_base = cgroup_basename(mprocname);
				if (strcmp(mprocname, procname) && strcmp(mproc_base, procname)) {
					uid = CGRULE_INVALID;
					gid = CGRULE_INVALID;
					matched = false;
					free(mproc_base);
					continue;
				}
				free(mproc_base);
			}
		}

		/*
		 * Now, we're either caching rules or we found a match.
		 * Either way, copy everything into a new rule and push it
		 * into the list.
		 */
		newrule = calloc(1, sizeof(struct cgroup_rule));
		if (!newrule) {
			cgroup_err("out of memory? Error was: %s\n", strerror(errno));
			last_errno = errno;
			ret = ECGOTHER;
			goto close;
		}

		newrule->uid = uid;
		newrule->gid = gid;
		newrule->is_ignore = false;

		len_username = min(len_username, sizeof(newrule->username) - 1);
		strncpy(newrule->username, user, len_username);
		newrule->username[sizeof(newrule->username) - 1] = '\0';

		if (len_procname) {
			newrule->procname = strdup(procname);
			if (!newrule->procname) {
				cgroup_err("strdup failed to allocate memory %s\n",
					   strerror(errno));
				free(newrule);
				last_errno = errno;
				ret = ECGOTHER;
				goto close;
			}
		} else {
			newrule->procname = NULL;
		}
		strncpy(newrule->destination, destination, sizeof(newrule->destination) - 1);
		newrule->destination[sizeof(newrule->destination) - 1] = '\0';

		if (has_options) {
			ret = cgroup_parse_rules_options(options, newrule);
			if (ret < 0)
				goto destroyrule;
		}

		newrule->next = NULL;

		/* Parse the controller list, and add that to newrule too. */
		stok_buff = strtok(controllers, ",");
		if (!stok_buff) {
			cgroup_err("failed to parse controllers on line %d\n", linenum);
			goto destroyrule;
		}

		i = 0;
		do {
			if (i >= MAX_MNT_ELEMENTS) {
				cgroup_err("too many controllers listed on line %d\n", linenum);
				goto destroyrule;
			}

			newrule->controllers[i] =
				strndup(stok_buff, strlen(stok_buff) + 1);
			if (!(newrule->controllers[i])) {
				cgroup_err("out of memory? Error was: %s\n", strerror(errno));
				goto destroyrule;
			}
			i++;
		} while ((stok_buff = strtok(NULL, ",")));

		/* Now, push the rule. */
		if (lst->head == NULL) {
			lst->head = newrule;
			lst->tail = newrule;
		} else {
			lst->tail->next = newrule;
			lst->tail = newrule;
		}

		cgroup_dbg("Added rule %s (UID: %d, GID: %d) -> %s for controllers:",
			   lst->tail->username, lst->tail->uid, lst->tail->gid,
			   lst->tail->destination);

		for (i = 0; lst->tail->controllers[i]; i++)
			cgroup_dbg(" %s", lst->tail->controllers[i]);
		cgroup_dbg("\n");
	}

	/* If we make it here, there were no errors. */
	cgroup_dbg("Parsing of configuration file complete.\n\n");
	ret = (matched && !cache) ? -1 : 0;
	goto close;

destroyrule:
	cgroup_free_rule(newrule);

parsefail:
	ret = ECGRULESPARSEFAIL;

close:
	fclose(fp);
finish:
	return ret;
}

/**
 * Parse CGRULES_CONF_FILE and all files in CGRULES_CONF_FILE_DIR.
 * If CGRULES_CONF_FILE_DIR does not exists or can not be read, parse only
 * CGRULES_CONF_FILE. This way we keep the back compatibility.
 *
 * Original description of this function moved to cgroup_parse_rules_file.
 * Also cloned and all occurences of file changed to files.
 *
 * Parse the configuration files that maps UID/GIDs to cgroups. If ever the
 * configuration files are modified, applications should call this function to
 * load the new configuration rules. The function caller is responsible for
 * calling free() on each rule in the list.
 *
 * The cache parameter alters the behavior of this function.  If true, this
 * function will read the entire content of all configuration files and store
 * the results in rl (global rules list). If false, this function will only
 * parse until it finds a file and a rule matching the given UID or GID.
 * The remaining files are skipped. It will store this rule in trl, as well as
 * any children rules (rules that begin with a %) that it has.
 *
 * Files can be read in an random order so the first match must not be
 * dependent on it. Thus construct the rules the way not to break this
 * assumption.
 *
 * This function is NOT thread safe!
 *	@param cache True to cache rules, else false
 *	@param muid If cache is false, the UID to match against
 *	@param mgid If cache is false, the GID to match against
 *	@return 0 on success, -1 if no cache and match found, > 0 on error.
 * TODO: Make this function thread safe!
 */
static int cgroup_parse_rules(bool cache, uid_t muid, gid_t mgid, const char *mprocname)
{
	/* Pointer to the list that we're using */
	struct cgroup_rule_list *lst = NULL;

	/* Directory variables */
	const char *dirname = CGRULES_CONF_DIR;
	struct dirent *item;
	char *tmp;
	int sret;
	DIR *d;

	int ret;

	/* Determine which list we're using. */
	if (cache)
		lst = &rl;
	else
		lst = &trl;

	/* If our list already exists, clean it. */
	if (lst->head)
		cgroup_free_rule_list(lst);

	pthread_rwlock_wrlock(&rl_lock);

	/* Parse CGRULES_CONF_FILE configuration file (back compatibility). */
	ret = cgroup_parse_rules_file(CGRULES_CONF_FILE, cache, muid, mgid, mprocname);

	/*
	 * if match (ret = -1), stop parsing other files,
	 * just return or ret > 0 => error
	 */
	if (ret != 0) {
		pthread_rwlock_unlock(&rl_lock);
		return ret;
	}

	/* Continue parsing */
	d = opendir(dirname);
	if (!d) {
		cgroup_warn("Failed to open directory %s: %s\n", dirname, strerror(errno));

		/*
		 * Cannot read directory. However, CGRULES_CONF_FILE is
		 * succesfully parsed. Thus return as a success for back
		 * compatibility.
		 */
		pthread_rwlock_unlock(&rl_lock);

		return 0;
	}

	/* Read all files from CGRULES_CONF_FILE_DIR */
	do {
		item = readdir(d);
		if (item && (item->d_type == DT_REG || item->d_type == DT_LNK)) {

			sret = asprintf(&tmp, "%s/%s", dirname, item->d_name);
			if (sret < 0) {
				cgroup_err("Out of memory\n");

				/*
				 * Cannot read directory.
				 * However, CGRULES_CONF_FILE is succesfully
				 * parsed. Thus return as a success for back
				 * compatibility.
				 */
				ret = 0;
				goto unlock_list;
			}

			cgroup_dbg("Parsing cgrules file: %s\n", tmp);
			ret = cgroup_parse_rules_file(tmp, cache, muid, mgid, mprocname);

			free(tmp);

			/* Match with cache disabled? */
			if (ret != 0)
				goto unlock_list;
		}
		if (!item && errno) {
			cgroup_warn("cannot read %s: %s\n", dirname, strerror(errno));
			/*
			 * Cannot read an item.
			 * But continue for back compatibility as a success.
			 */
			ret = 0;
			goto unlock_list;
		}
	} while (item != NULL);

unlock_list:
	closedir(d);
	pthread_rwlock_unlock(&rl_lock);

	return ret;
}

int cg_add_duplicate_mount(struct cg_mount_table_s *item, const char *path)
{
	struct cg_mount_point *mount, *it;

	mount = malloc(sizeof(struct cg_mount_point));
	if (!mount) {
		last_errno = errno;
		return ECGOTHER;
	}
	mount->next = NULL;

	strncpy(mount->path, path, sizeof(mount->path));
	mount->path[sizeof(mount->path)-1] = '\0';

	/*
	 * Add the mount point to the end of the list. Assuming the list is
	 * short, no optimization is done.
	 */
	it = &item->mount;
	while (it->next)
		it = it->next;

	it->next = mount;

	return 0;
}

/*
 * Tries to find if any controller in cg_mount_table have already mounted on
 * the mount_path and if mounted sets the matching controller idx share_mnt
 * flag and Return 1 or 0 otherwise.
 */
static int cgroup_set_cg_mnt_tbl_shared_mnt(char *mount_path, int *mnt_tbl_idx)
{
	int i, shared_mnt = 0;

	/* Check if controllers share mount points */
	for  (i = 0; i < *mnt_tbl_idx; i++) {
		if (strncmp(mount_path, cg_mount_table[i].mount.path, FILENAME_MAX) == 0) {
			cg_mount_table[i].shared_mnt = 1;
			shared_mnt = 1;
			break;
		}
	}

	return shared_mnt;
}

static void cgroup_cg_mount_table_append(const char *name, const char *mount_path,
					 enum cg_version_t version, int *mnt_tbl_idx,
					 const char *mnt_opts, int shared_mnt)
{
	int i = *mnt_tbl_idx;

	strncpy(cg_mount_table[i].name,	name, CONTROL_NAMELEN_MAX);
	cg_mount_table[i].name[CONTROL_NAMELEN_MAX-1] = '\0';

	strncpy(cg_mount_table[i].mount.path, mount_path, FILENAME_MAX);
	cg_mount_table[i].mount.path[FILENAME_MAX-1] = '\0';

	cg_mount_table[i].shared_mnt = shared_mnt;
	cg_mount_table[i].version = version;
	cg_mount_table[i].mount.next = NULL;

	cgroup_dbg("Found cgroup option %s, count %d\n", mnt_opts, i);

	(*mnt_tbl_idx)++;
}

/**
 * Process a cgroup v1 mount and add it to cg_mount_table if it's not a
 * duplicate.
 *
 *	@param controllers List of controllers from /proc/cgroups
 *	@param ent File system description of cgroup mount being processed
 *	@param mnt_tbl_idx cg_mount_table index
 */
STATIC int cgroup_process_v1_mnt(char *controllers[], struct mntent *ent, int *mnt_tbl_idx)
{
	char *strtok_buffer = NULL, *mntopt = NULL;
	int shared_mnt, duplicate;
	int i, j, ret = 0;
	char c = 0;

	for (i = 0; controllers[i] != NULL; i++) {
		mntopt = hasmntopt(ent, controllers[i]);

		if (!mntopt)
			continue;

		c = mntopt[strlen(controllers[i])];

		if (c != '\0' && c != ',')
			continue;

		cgroup_dbg("found %s in %s\n", controllers[i], ent->mnt_opts);

		/* Check if controllers share mount points */
		shared_mnt = cgroup_set_cg_mnt_tbl_shared_mnt(ent->mnt_dir, mnt_tbl_idx);

		/* Do not have duplicates in mount table */
		duplicate = 0;
		for  (j = 0; j < *mnt_tbl_idx; j++) {
			if (strncmp(controllers[i], cg_mount_table[j].name, FILENAME_MAX) == 0) {
				duplicate = 1;
				break;
			}
		}

		if (duplicate) {
			cgroup_dbg("controller %s is already mounted on %s\n", mntopt,
				   cg_mount_table[j].mount.path);
			ret = cg_add_duplicate_mount(&cg_mount_table[j], ent->mnt_dir);
			if (ret)
				goto out;
			/* Continue with next controller */
			continue;
		}

		cgroup_cg_mount_table_append(controllers[i], ent->mnt_dir, CGROUP_V1, mnt_tbl_idx,
					     ent->mnt_opts, shared_mnt);

		if ((*mnt_tbl_idx) >= CG_CONTROLLER_MAX)
			goto out;
	}

	/* Doesn't match the controller. Check if it is a named hierarchy. */
	mntopt = hasmntopt(ent, "name");

	if (mntopt) {
		mntopt = strtok_r(mntopt, ",", &strtok_buffer);
		if (!mntopt)
			goto out;

#ifdef OPAQUE_HIERARCHY
		/* Ignore the opaque hierarchy. */
		if (strcmp(mntopt, OPAQUE_HIERARCHY) == 0)
			goto out;
#endif
		/* Check if controllers share mount points */
		shared_mnt = cgroup_set_cg_mnt_tbl_shared_mnt(ent->mnt_dir, mnt_tbl_idx);

		/* Check if it is a duplicate */
		duplicate = 0;
		for (j = 0; j < *mnt_tbl_idx; j++) {
			if (strncmp(mntopt, cg_mount_table[j].name, FILENAME_MAX) == 0) {
				duplicate = 1;
				break;
			}
		}

		if (duplicate) {
			cgroup_dbg("controller %s is already mounted on %s\n", mntopt,
				   cg_mount_table[j].mount.path);
			ret = cg_add_duplicate_mount(&cg_mount_table[j], ent->mnt_dir);
			goto out;
		}

		cgroup_cg_mount_table_append(mntopt, ent->mnt_dir, CGROUP_V1, mnt_tbl_idx,
					     ent->mnt_opts, shared_mnt);
	}

out:
	return ret;
}

/**
 * Process a cgroup v2 mount and add it to cg_mount_table if it's not a
 * duplicate.
 *
 *	@param ent File system description of cgroup mount being processed
 *	@param mnt_tbl_idx cg_mount_table index
 */
STATIC int cgroup_process_v2_mnt(struct mntent *ent, int *mnt_tbl_idx)
{
	char *ret_c = NULL, line[CGV2_CONTROLLERS_LL_MAX], *stok_buff = NULL;
	char *controller = NULL, *controllers = NULL;
	char cgroup_controllers_path[FILENAME_MAX];
	int ret = 0, i, duplicate, shared_mnt;
	FILE *fp = NULL;

	/*
	 * Save off this mount point.  This may be used later to
	 * build the cg_path.
	 */
	strncpy(cg_cgroup_v2_mount_path, ent->mnt_dir, FILENAME_MAX-1);
	cg_cgroup_v2_mount_path[FILENAME_MAX-1] = '\0';

	/* determine what v2 controllers are available on this mount */
	snprintf(cgroup_controllers_path, FILENAME_MAX, "%s/%s", ent->mnt_dir,
		 CGV2_CONTROLLERS_FILE);

	fp = fopen(cgroup_controllers_path, "re");
	if (!fp) {
		ret = ECGOTHER;
		goto out;
	}

	ret_c = fgets(line, CGV2_CONTROLLERS_LL_MAX, fp);
	if (ret_c == NULL) {
		struct cg_mount_point *tmp, *t;

		ret = ECGEOF;

		tmp = malloc(sizeof(struct cg_mount_point));
		if (tmp == NULL) {
			last_errno = errno;
			ret = ECGOTHER;
			goto out;
		}

		strncpy(tmp->path, cg_cgroup_v2_mount_path, sizeof(tmp->path) - 1);
		tmp->path[sizeof(tmp->path)-1] = '\0';
		tmp->next = NULL;

		t = cg_cgroup_v2_empty_mount_paths;
		if (t == NULL) {
			cg_cgroup_v2_empty_mount_paths = tmp;
			goto out;
		}

		while(t->next != NULL)
			t = t->next;
		t->next = tmp;

		goto out;
	}

	/* Remove the trailing newline */
	ret_c[strlen(ret_c) - 1] = '\0';

	/*
	 * The "cgroup" controller is a pseudo-controller that has settings that a user may
	 * wish to read/modify.  Add it to our cg_mount_table so that it can be manipulated
	 * like other "normal" controllers
	 */
	controllers = malloc(strlen(ret_c) + strlen(CGROUP_FILE_PREFIX) + 2);
	if (!controllers) {
		ret = ECGOTHER;
		goto out;
	}

	sprintf(controllers, "%s %s", ret_c, CGROUP_FILE_PREFIX);

	/*
	 * cgroup.controllers returns a list of available controllers in
	 * the following format:
	 *	cpuset cpu io memory pids rdma
	 */
	controller = strtok_r(controllers, " ", &stok_buff);
	do {
		/* Check if controllers share mount points */
		shared_mnt = cgroup_set_cg_mnt_tbl_shared_mnt(ent->mnt_dir, mnt_tbl_idx);

		/* Do not have duplicates in mount table */
		duplicate = 0;
		for  (i = 0; i < *mnt_tbl_idx; i++) {
			if (strncmp(cg_mount_table[i].name, controller, FILENAME_MAX) == 0) {
				duplicate = 1;
				break;
			}
		}

		if (duplicate) {
			cgroup_dbg("controller %s is already mounted on %s\n", controller,
				   cg_mount_table[i].mount.path);

			ret = cg_add_duplicate_mount(&cg_mount_table[i], ent->mnt_dir);
			if (ret)
				break;

			continue;
		}

		/* This controller is not in the mount table.  add it */
		cgroup_cg_mount_table_append(controller, ent->mnt_dir, CGROUP_V2, mnt_tbl_idx,
					     controller, shared_mnt);

		if ((*mnt_tbl_idx) >= CG_CONTROLLER_MAX)
			goto out;
	} while ((controller = strtok_r(NULL, " ", &stok_buff)));

out:
	if (fp)
		fclose(fp);

	if (controllers)
		free(controllers);

	return ret;
}

/*
 * Free global variables filled by previous cgroup_init(). This function
 * should be called with cg_mount_table_lock taken.
 */
static void cgroup_free_cg_mount_table(void)
{
	struct cg_mount_point *mount, *tmp;
	int i;

	for (i = 0; cg_mount_table[i].name[0] != '\0'; i++) {
		mount = cg_mount_table[i].mount.next;

		while (mount) {
			tmp = mount;
			mount = mount->next;
			free(tmp);
		}
	}

	memset(&cg_mount_table, 0, sizeof(cg_mount_table));
	memset(&cg_cgroup_v2_mount_path, 0, sizeof(cg_cgroup_v2_mount_path));
	memset(&cg_cgroup_v2_empty_mount_paths, 0, sizeof(cg_cgroup_v2_empty_mount_paths));
}

/*
 * Parses the mount options of the given mount point and checks for the
 * option in the list of mount options and sets is_set accordingly.
 * @mnt: Mount point name to search for mount points.
 * @mnt_opt: Mount option to be searched.
 * @is_set: Set to 1, when mount option is found, 0 otherwise.
 *
 * Returns 0, in case of success and ECGOTHER on failure.
 */
static int check_mount_point_opt(const char *mnt, const char *mnt_opt, int * const is_set)
{
	struct mntent *ent, *temp_ent = NULL;
	char mntent_buffer[4 * FILENAME_MAX];
	char *mntopt = NULL;
	char mnt_opt_delim;
	FILE *proc_mount;
	int ret = 0;

	if (!mnt || !mnt_opt || !is_set)
		return ECGINVAL;

	proc_mount = setmntent(mnt, "r");
	if (!proc_mount) {
		cgroup_err("cannot open %s: %s\n", mnt, strerror(errno));
		last_errno = errno;
		ret = ECGOTHER;
		goto err;
	}

	temp_ent = (struct mntent *) malloc(sizeof(struct mntent));
	if (!temp_ent) {
		last_errno = errno;
		ret = ECGOTHER;
		goto err;
	}

	ent = getmntent_r(proc_mount, temp_ent,	mntent_buffer, sizeof(mntent_buffer));
	if (!ent) {
		last_errno = errno;
		ret = ECGOTHER;
		goto err;
	}

	*is_set = 0;
	while((mntopt = hasmntopt(ent, mnt_opt))) {
		mnt_opt_delim = mntopt[strlen(mnt_opt)];
		if (mnt_opt_delim == '\0' || mnt_opt_delim == ',') {
			*is_set  = 1;
			break;
		}
	}

	if (*is_set == 0) {
		cgroup_dbg("%s, not found in the list of ", mnt_opt);
		cgroup_cont("mount options of %s\n", mnt);
	}

err:
	if (proc_mount)
		endmntent(proc_mount);

	if (temp_ent)
		free(temp_ent);

	return ret;
}

/*
 * Reads /proc/cgroups and populates the controllers/subsys_name. This
 * function should be called with cg_mount_table_lock taken.
 */
static int cgroup_populate_controllers(char *controllers[CG_CONTROLLER_MAX])
{
	int hierarchy, num_cgroups, enabled;
	char subsys_name[FILENAME_MAX];
	char mnt_opt[] = "subset=pid";
	FILE *proc_cgroup;
	char *buf = NULL;
	int mnt_opt_set;
	int ret = 0;
	int err, i = 0;

	proc_cgroup = fopen("/proc/cgroups", "re");
	if (!proc_cgroup) {
		cgroup_warn("cannot open /proc/cgroups: %s\n", strerror(errno));
		ret = check_mount_point_opt("/proc/self/mounts", mnt_opt, &mnt_opt_set);
		if (ret)
			goto err;

		if (!mnt_opt_set)
			ret = ECGINVAL;
		/*
		 * /proc, mounted with subset=pid is valid. cgroup v2 doesn't
		 * depend on /proc/cgroups to parse the available controllers.
		 */
		goto err;
	}

	/*
	 * The first line of the file has stuff we are not interested in.
	 * So just read it and discard the information.
	 */
	buf = malloc(CGV2_CONTROLLERS_LL_MAX);
	if (!buf) {
		last_errno = errno;
		ret = ECGOTHER;
		goto err;
	}

	if (!fgets(buf, CGV2_CONTROLLERS_LL_MAX, proc_cgroup)) {
		cgroup_err("cannot read /proc/cgroups: %s\n", strerror(errno));
		last_errno = errno;
		ret = ECGOTHER;
		goto err;
	}

	while (!feof(proc_cgroup)) {
		/*
		 * check Linux Kernel sources/kernel/cgroup/cgroup.c cgroup_init_early(),
		 * MAX_CGROUP_TYPE_NAMELEN check for details on why 32 is used.
		 */
		err = fscanf(proc_cgroup, "%32s %d %d %d", subsys_name, &hierarchy, &num_cgroups,
			     &enabled);
		if (err < 0)
			break;

		controllers[i] = strdup(subsys_name);
		if (controllers[i] == NULL) {
			last_errno = errno;
			ret = ECGOTHER;
			break;
		}
		i++;
	}

err:
	if (proc_cgroup)
		fclose(proc_cgroup);

	if (buf)
		free(buf);

	if (ret != 0) {
		for (i = 0; controllers[i]; i++) {
			free(controllers[i]);
			controllers[i] = NULL;
		}
	}

	return ret;
}

/*
 * Reads /proc/self/mounts and populates the cgroup v1/v2 mount points into the
 * global cg_mount_table.
 * This function should be called with cg_mount_table_lock taken.
 */
static int cgroup_populate_mount_points(char *controllers[CG_CONTROLLER_MAX])
{
	char mntent_buffer[4 * FILENAME_MAX];
	struct mntent *ent, *temp_ent = NULL;
	int found_mnt = 0;
	FILE *proc_mount;
	int ret = 0;

	proc_mount = fopen("/proc/self/mounts", "re");
	if (proc_mount == NULL) {
		cgroup_err("cannot open /proc/self/mounts: %s\n", strerror(errno));
		last_errno = errno;
		ret = ECGOTHER;
		goto err;
	}

	temp_ent = (struct mntent *) malloc(sizeof(struct mntent));
	if (!temp_ent) {
		last_errno = errno;
		ret = ECGOTHER;
		goto err;
	}

	while ((ent = getmntent_r(proc_mount, temp_ent,	mntent_buffer,
				  sizeof(mntent_buffer))) != NULL) {

		if (strcmp(ent->mnt_type, "cgroup") == 0) {
			if (controllers[0] == NULL) {
				cgroup_err("cgroup v1 requires /proc/cgroups, check if /proc ");
				cgroup_cont("is mounted with subset=pid option.\n");
				ret = ECGINVAL;
				goto err;
			}

			ret = cgroup_process_v1_mnt(controllers, ent, &found_mnt);
			if (ret)
				goto err;

			if (found_mnt >= CG_CONTROLLER_MAX)
				break;

			continue;
		}

		if (strcmp(ent->mnt_type, "cgroup2") == 0) {
			ret = cgroup_process_v2_mnt(ent, &found_mnt);
			if (ret == ECGEOF) {
			/* The controllers file was empty.  Ignore and move on. */
				ret = 0;
				continue;
			}

			if (ret)
				goto err;

			if (found_mnt >= CG_CONTROLLER_MAX)
				break;
		}
	}

	if (!found_mnt)
		ret = ECGROUPNOTMOUNTED;

	if (found_mnt >= CG_CONTROLLER_MAX) {
		cgroup_err("Mount points exceeds CG_CONTROLLER_MAX");
		ret = ECGMAXVALUESEXCEEDED;
		/*
		 * There are loops in the libcgroup codebase that expect
		 * there to be a null name entry at the end of the
		 * cg_mount_table[].
		 */
		cg_mount_table[CG_CONTROLLER_MAX - 1].name[0] = '\0';
	}

err:
	if (proc_mount)
		fclose(proc_mount);

	if (temp_ent)
		free(temp_ent);

	return ret;
}

/**
 * cgroup_init(), initializes the MOUNT_POINT.
 *
 * This code is theoretically thread safe now. Its not really tested so it can
 * blow up. If does for you, please let us know with your test case and we can
 * really make it thread safe.
 */
int cgroup_init(void)
{
	static char *controllers[CG_CONTROLLER_MAX];
	int ret = 0;
	int i;

	cgroup_set_default_logger(-1);

	pthread_rwlock_wrlock(&cg_mount_table_lock);

	/* Free global variables filled by previous cgroup_init() */
	cgroup_free_cg_mount_table();

	ret = cgroup_populate_controllers(controllers);
	if (ret)
		goto unlock_exit;

	ret = cgroup_populate_mount_points(controllers);
	if (ret)
		goto unlock_exit;

	cgroup_initialized = 1;

unlock_exit:
	for (i = 0; controllers[i]; i++) {
		free(controllers[i]);
		controllers[i] = NULL;
	}

	pthread_rwlock_unlock(&cg_mount_table_lock);

	return ret;
}

static int cg_test_mounted_fs(void)
{
	char mntent_buff[4 * FILENAME_MAX];
	struct mntent *temp_ent = NULL;
	struct mntent *ent = NULL;
	FILE *proc_mount = NULL;
	int ret = 1;

	proc_mount = fopen("/proc/self/mounts", "re");
	if (proc_mount == NULL)
		return 0;

	temp_ent = (struct mntent *) malloc(sizeof(struct mntent));
	if (!temp_ent) {
		/* We just fail at the moment. */
		fclose(proc_mount);
		return 0;
	}

	ent = getmntent_r(proc_mount, temp_ent, mntent_buff, sizeof(mntent_buff));
	if (!ent) {
		ret = 0;
		goto done;
	}

	while (strcmp(ent->mnt_type, "cgroup") != 0 &&
	       strcmp(ent->mnt_type, "cgroup2") != 0) {
		ent = getmntent_r(proc_mount, temp_ent, mntent_buff, sizeof(mntent_buff));
		if (ent == NULL) {
			ret = 0;
			goto done;
		}
	}
done:
	fclose(proc_mount);
	free(temp_ent);

	return ret;
}

static inline pid_t cg_gettid(void)
{
	return syscall(__NR_gettid);
}

static char *cg_concat_path(const char *pref, const char *suf, char *path)
{
	if ((suf[strlen(suf)-1] == '/') || ((strlen(suf) == 0) && (pref[strlen(pref)-1] == '/')))
		snprintf(path, FILENAME_MAX, "%s%s", pref, suf+((suf[0] == '/') ? 1 : 0));
	else
		snprintf(path, FILENAME_MAX, "%s%s/", pref, suf+((suf[0] == '/') ? 1 : 0));

	path[FILENAME_MAX-1] = '\0';

	return path;
}

/* Call with cg_mount_table_lock taken */
/* path value have to have size at least FILENAME_MAX */
char *cg_build_path_locked(const char *name, char *path, const char *type)
{
	/*
	 * len is the allocation size for path, that stores:
	 * cg_mount_table[i].mount.path + '/' + cg_namespace_table[i] + '/'
	 */
	int i, ret, len = (FILENAME_MAX * 2) + 2;
	char *tmp_systemd_default_cgroup, *_path = NULL;

	/*
	 * systemd_default_cgroup can't be clobbered.   The user may pass
	 * multiple cgroups, hence use temporary variable for manipulations
	 * for example:
	 * cgget -g cpu:/ -g cpu:cgrp1 -g cpu:/cgrp2
	 */
	tmp_systemd_default_cgroup = calloc(1, (sizeof(char) * len));
	if (!tmp_systemd_default_cgroup) {
		cgroup_err("Failed to allocate memory for tmp_systemd_default_cgroup\n");
		goto out;
	}

#ifdef WITH_SYSTEMD
	/*
	 * If the user specifies the name as /<cgroup-name>, they are
	 * effectively overriding the systemd_default_cgroup but if the name
	 * is "/", the cgroup root path is systemd_default_cgroup
	 */
	if (strlen(systemd_default_cgroup) && name && name[0] == '/' && name[1] != '\0')
		tmp_systemd_default_cgroup[0] = '\0';
	else
		snprintf(tmp_systemd_default_cgroup, len, "%s/", systemd_default_cgroup);

	/* allocate more space for systemd_default_cgroup + '/' */
	len += (FILENAME_MAX + 1);
#endif
	/*
	 * Recent gcc are unhappy when sizeof(dest) <= sizeof(src) with
	 * snprintf()'s.  Alternative is to use multiple strncpy()/strcat(),
	 * work around it by allocating large temporary buffer _path and
	 * copying the constructed _path into path.
	 */
	_path = malloc(len);
	if (!_path) {
		cgroup_err("Failed to allocate memory for _path\n");
		goto out;
	}

	/*
	 * If no type is specified, and there's a valid cgroup v2 mount, then
	 * build up a path to this mount (and cgroup name if supplied).
	 * This can be used to create a cgroup v2 cgroup that's not attached to
	 * any controller.
	 */
	if (!type && strlen(cg_cgroup_v2_mount_path) > 0) {
		ret = snprintf(_path, len, "%s/%s", cg_cgroup_v2_mount_path,
			       tmp_systemd_default_cgroup);
		if (ret >= FILENAME_MAX)
			cgroup_dbg("filename too long: %s", _path);

		strncpy(path, _path, FILENAME_MAX - 1);
		path[FILENAME_MAX - 1] = '\0';

		if (name) {
			char *tmp;

			tmp = strdup(path);
			if (tmp == NULL) {
				path = NULL;
				goto out;
			}

			cg_concat_path(tmp, name, path);
			free(tmp);
		}
		goto out;
	}

	for (i = 0; cg_mount_table[i].name[0] != '\0'; i++) {
		/* Two ways to successfully move forward here:
		 * 1. The "type" controller matches the name of a mounted
		 *    controller
		 * 2. The "type" controller requested is "cgroup" and there's
		 *    a "real" controller mounted as cgroup v2
		 */
		if ((type && strcmp(cg_mount_table[i].name, type) == 0) ||
		    (type && strcmp(type, CGROUP_FILE_PREFIX) == 0 &&
		     cg_mount_table[i].version == CGROUP_V2)) {

			if (cg_namespace_table[i])
				ret = snprintf(_path, len, "%s/%s%s/", cg_mount_table[i].mount.path,
					       tmp_systemd_default_cgroup, cg_namespace_table[i]);
			else
				ret = snprintf(_path, len, "%s/%s", cg_mount_table[i].mount.path,
					       tmp_systemd_default_cgroup);

			if (ret >= FILENAME_MAX)
				cgroup_dbg("filename too long: %s", _path);

			strncpy(path, _path, FILENAME_MAX - 1);
			path[FILENAME_MAX - 1] = '\0';

			if (name) {
				char *tmp;

				tmp = strdup(path);
				if (tmp == NULL)
					break;

				cg_concat_path(tmp, name, path);
				free(tmp);
			}
			goto out;
		}
	}
	path = NULL;

out:
	if (_path)
		free(_path);

	if (tmp_systemd_default_cgroup)
		free(tmp_systemd_default_cgroup);

	return path;
}

char *cg_build_path(const char *name, char *path, const char *type)
{
	pthread_rwlock_rdlock(&cg_mount_table_lock);
	path = cg_build_path_locked(name, path, type);
	pthread_rwlock_unlock(&cg_mount_table_lock);

	return path;
}

static int cgroup_get_cg_type(const char * const path, char * const type,
			      size_t type_sz)
{
	char cg_type_path[FILENAME_MAX];
	char cg_type[CGV2_CONTROLLERS_LL_MAX];
	int len, err = 0;
	FILE *fp = NULL;

	snprintf(cg_type_path, FILENAME_MAX, "%scgroup.type", path);
	fp = fopen(cg_type_path, "re");
	if (!fp) {
		if (errno == ENOENT) {
			/* file cgroup.type, doesn't exist for root cgroup. */
			snprintf(type, type_sz, "cgroup.procs");
			goto out;
		} else {
			cgroup_warn("failed to open file %s: %s\n", cg_type_path, strerror(errno));
			err = ECGOTHER;
			goto out;
		}
	}

	if (fgets(cg_type, CGV2_CONTROLLERS_LL_MAX, fp) == NULL) {
		cgroup_warn("failed to read file %s: %s\n", cg_type_path, strerror(errno));
		err = ECGOTHER;
		goto out;
	}

	len = strlen(cg_type) - 1;
	/*
	 * Append cgroup.threads to the path, if the cgroup.type is threaded
	 * and cgroup.procs for type domain, domain threaded. domain type is
	 * used for regular cgroup and domain threaded for root of threaded
	 * cgroup v2 subtree.  Another possible type is domain invalid, it's
	 * an invalid state, under the threaded subtree.
	 */
	if (strncmp(cg_type, "domain", len) == 0 ||
	    strncmp(cg_type, "domain threaded", len) == 0 ||
	    strncmp(cg_type, "domain invalid", len) == 0) {
		snprintf(type, type_sz, "cgroup.procs");
	} else if (strncmp(cg_type, "threaded", len) == 0) {
		snprintf(type, type_sz, "cgroup.threads");
	} else {
		cgroup_warn("invalid %scgroup.type: %s\n", path, cg_type);
		err = ECGOTHER;
	}

out:
	if (fp)
		fclose(fp);

	return err;
}

int cgroup_build_tasks_procs_path(char * const path, size_t path_sz, const char * const cg_name,
				  const char * const ctrl_name)
{
	enum cg_version_t version;
	char cg_type[CGV2_CONTROLLERS_LL_MAX];
	int err = ECGOTHER;

	if (!cg_build_path(cg_name, path, ctrl_name))
		goto error;

	err = cgroup_get_controller_version(ctrl_name, &version);
	if (err)
		goto error;

	switch (version) {
	case CGROUP_V1:
		strncat(path, "tasks", path_sz - strlen(path));
		err = 0;
		break;
	case CGROUP_V2:
		err = cgroup_get_cg_type(path, cg_type, sizeof(cg_type));
		if (err)
			goto error;

		strncat(path, cg_type, path_sz - strlen(path));
		break;
	default:
		err = ECGOTHER;
		break;
	}

error:
	if (err)
		path[0] = '\0';

	cgroup_dbg("cgroup build procs path: %s\n", path);

	return err;
}

STATIC int cgroupv2_controller_enabled(const char * const cg_name, const char * const ctrl_name)
{
	char path[FILENAME_MAX] = {0};
	char *parent = NULL, *dname;
	enum cg_version_t version;
	bool enabled;
	int error;

	error = cgroup_get_controller_version(ctrl_name, &version);
	if (error)
		return error;

	if (version != CGROUP_V2)
		return 0;

	if (ctrl_name == NULL)
		/* cgroup v2 supports cgroups with no controllers. */
		return 0;

	if (strncmp(cg_name, "/", strlen(cg_name)) == 0)
		/*
		 * The root cgroup has been requested.  All version 2
		 * controllers are enabled on the root cgroup.
		 */
		return 0;

	if (!cg_build_path(cg_name, path, ctrl_name))
		goto err;

	parent = strdup(path);
	if (!parent) {
		error = ECGOTHER;
		goto err;
	}

	dname = dirname(parent);

	error = cgroupv2_get_subtree_control(dname, ctrl_name, &enabled);
	if (error)
		goto err;

	if (enabled)
		error = 0;
err:
	if (parent)
		free(parent);

	return error;
}

static int __cgroup_attach_task_pid(char *path, pid_t tid)
{
	FILE *tasks = NULL;
	int ret = 0;

	tasks = fopen(path, "we");
	if (!tasks) {
		switch (errno) {
		case EPERM:
			ret = ECGROUPNOTOWNER;
			break;
		case ENOENT:
			ret = ECGROUPNOTEXIST;
			break;
		default:
			ret = ECGROUPNOTALLOWED;
		}
		goto err;
	}
	ret = fprintf(tasks, "%d", tid);
	if (ret < 0) {
		last_errno = errno;
		ret = ECGOTHER;
		goto err;
	}
	ret = fflush(tasks);
	if (ret) {
		last_errno = errno;
		ret = ECGOTHER;
		goto err;
	}
	fclose(tasks);
	return 0;
err:
	cgroup_warn("cannot write tid %d to %s:%s\n", tid, path, strerror(errno));
	if (tasks)
		fclose(tasks);
	return ret;
}

/**
 *  cgroup_attach_task_pid is used to assign tasks to a cgroup.
 *  struct cgroup *cgroup: The cgroup to assign the thread to.
 *  pid_t tid: The thread to be assigned to the cgroup.
 *
 *  returns 0 on success.
 *  returns ECGROUPNOTOWNER if the caller does not have access to the cgroup.
 *  returns ECGROUPNOTALLOWED for other causes of failure.
 */
int cgroup_attach_task_pid(struct cgroup *cgroup, pid_t tid)
{
	char path[FILENAME_MAX] = {0};
	char *controller_name;
	int empty_cgroup = 0;
	int i, ret = 0;

	if (!cgroup_initialized) {
		cgroup_warn("libcgroup is not initialized\n");
		return ECGROUPNOTINITIALIZED;
	}

	/* if the cgroup is NULL, attach the task to the root cgroup. */
	if (!cgroup) {
		pthread_rwlock_rdlock(&cg_mount_table_lock);
		for (i = 0; i < CG_CONTROLLER_MAX && cg_mount_table[i].name[0] != '\0'; i++) {
			ret = cgroup_build_tasks_procs_path(path, sizeof(path), NULL,
							    cg_mount_table[i].name);
			if (ret)
				return ret;

			ret = __cgroup_attach_task_pid(path, tid);
			if (ret) {
				pthread_rwlock_unlock(&cg_mount_table_lock);
				return ret;
			}
		}
		pthread_rwlock_unlock(&cg_mount_table_lock);
	} else {
		for (i = 0; i < cgroup->index; i++) {
			if (!cgroup_test_subsys_mounted(cgroup->controller[i]->name)) {
				cgroup_warn("subsystem %s is not mounted\n",
					    cgroup->controller[i]->name);
				return ECGROUPSUBSYSNOTMOUNTED;
			}
		}

		if (cgroup->index == 0)
			/* Valid empty cgroup v2 with no controllers added. */
			empty_cgroup = 1;

		for (i = 0, controller_name = NULL;
		     empty_cgroup > 0 || i < cgroup->index;
		     i++, empty_cgroup--) {

			if (cgroup->controller[i])
				controller_name = cgroup->controller[i]->name;

			ret = cgroupv2_controller_enabled(cgroup->name, controller_name);
			if (ret)
				return ret;

			ret = cgroup_build_tasks_procs_path(path, sizeof(path), cgroup->name,
							    controller_name);
			if (ret)
				return ret;

			ret = __cgroup_attach_task_pid(path, tid);
			if (ret)
				return ret;
		}
	}
	return 0;
}

/**
 * cgroup_attach_task is used to attach the current thread to a cgroup.
 * struct cgroup *cgroup: The cgroup to assign the current thread to.
 *
 * See cg_attach_task_pid for return values.
 */
int cgroup_attach_task(struct cgroup *cgroup)
{
	pid_t tid = cg_gettid();
	int error;

	error = cgroup_attach_task_pid(cgroup, tid);

	return error;
}

/**
 * cg_mkdir_p, emulate the mkdir -p command (recursively creating paths)
 * @path: path to create
 */
int cg_mkdir_p(const char *path)
{
	char *real_path = NULL;
	char *tmp_path = NULL;
	struct stat st;
	int ret = 0;
	int i = 0;
	char pos;

	real_path = strdup(path);
	if (!real_path) {
		last_errno = errno;
		return ECGOTHER;
	}

	do {
		while (real_path[i] != '\0' && real_path[i] == '/')
			i++;

		if (real_path[i] == '\0')
			break; /* The path ends with '/', ignore it. */

		while (real_path[i] != '\0' && real_path[i] != '/')
			i++;

		pos = real_path[i];
		real_path[i] = '\0';		/* Temporarily overwrite "/" */

		/* 0775 == S_IRWXU | S_IRWXG | S_IROTH | S_IXOTH */
		ret = mkdir(real_path, 0775);
		if (ret) {
			switch (errno) {
			case EEXIST:
				ret = 0;	/* Not fatal really */
				break;
			case EPERM:
				ret = ECGROUPNOTOWNER;
				goto done;
			case EROFS:
				/*
				 * Check if path exists, use tmp_path to
				 * keep Coverity happy
				 */
				tmp_path = real_path;
				ret = stat(tmp_path, &st);
				if (ret == 0)
					break;	/* Path exists */
			default: /* fallthrough */
				ret = ECGROUPNOTALLOWED;
				goto done;
			}
		}
		real_path[i] = pos;
	} while (real_path[i]);

done:
	free(real_path);

	return ret;
}

/*
 * create_control_group()
 * This is the basic function used to create the control group. This function
 * just makes the group. It does not set any permissions, or any control values.
 * The argument path is the fully qualified path name to make it generic.
 */
static int cg_create_control_group(const char *path)
{
	int error;

	if (!cg_test_mounted_fs())
		return ECGROUPNOTMOUNTED;

	error = cg_mkdir_p(path);
	return error;
}

/*
 * set_control_value()
 * This is the low level function for putting in a value in a control file.
 * This function takes in the complete path and sets the value in val in that file.
 */
static int cg_set_control_value(char *path, const char *val)
{
	char *str_val_start;
	char *str_val;
	int ctl_file;
	size_t len;
	char *pos;

	if (!cg_test_mounted_fs())
		return ECGROUPNOTMOUNTED;

	ctl_file = open(path, O_RDWR | O_CLOEXEC);

	if (ctl_file == -1) {
		if (errno == EPERM) {
			/*
			 * We need to set the correct error value, does the
			 * group exist but we don't have the subsystem mounted
			 * at that point, or is it that the group does not exist.
			 * So we check if the tasks file exist. Before that, we
			 * need to extract the path.
			 */
			char *path_dir_end;
			FILE *control_file;
			char *tasks_path;

			path_dir_end = strrchr(path, '/');
			if (path_dir_end == NULL)
				return ECGROUPVALUENOTEXIST;
			path_dir_end = '\0';

			/* task_path contain: $path/tasks */
			tasks_path = (char *)malloc(strlen(path) + 6 + 1);
			if (tasks_path == NULL) {
				last_errno = errno;
				return ECGOTHER;
			}
			strcpy(tasks_path, path);
			strcat(tasks_path, "/tasks");

			/* Test tasks file for read flag */
			control_file = fopen(tasks_path, "re");
			if (!control_file) {
				if (errno == ENOENT) {
					free(tasks_path);
					return ECGROUPSUBSYSNOTMOUNTED;
				}
			} else {
				fclose(control_file);
			}

			free(tasks_path);
			return ECGROUPNOTALLOWED;
		}
		return ECGROUPVALUENOTEXIST;
	}

	/*
	 * Split the multiline value into lines.  One line is a special
	 * case of multiline value.
	 */
	str_val = strdup(val);
	if (str_val == NULL) {
		last_errno = errno;
		close(ctl_file);
		return ECGOTHER;
	}

	str_val_start = str_val;
	pos = str_val;

	do {
		str_val = pos;
		pos = strchr(str_val, '\n');

		if (pos) {
			*pos = '\0';
			++pos;
		}

		len = strlen(str_val);
		if (len > 0) {
			if (write(ctl_file, str_val, len) == -1) {
				last_errno = errno;
				free(str_val_start);
				close(ctl_file);
				return ECGOTHER;
			}
		} else
			cgroup_warn("skipping empty line for %s\n", path);
	} while (pos);

	if (close(ctl_file)) {
		last_errno = errno;
		free(str_val_start);
		return ECGOTHER;
	}

	free(str_val_start);
	return 0;
}

/**
 * Walk the settings in controller and write their values to disk
 *
 * @param base The full path to the base of this cgroup
 * @param controller The controller whose values are being updated
 * @param ignore_non_dirty_values If set skips writing non-dirty controller settings
 */
STATIC int cgroup_set_values_recursive(const char * const base,
				       const struct cgroup_controller * const controller,
				       bool ignore_non_dirty_values)
{
	struct control_value *cv;
	struct stat path_stat;
	int ret, j, error = 0;
	char *path = NULL;

	for (j = 0; j < controller->index; j++) {
		cv = controller->values[j];

		/*
		 * ignore_non_dirty_values is set while writing into
		 * existing cgroup to modify controller settings and
		 * unset during cgroup creation.  The subtle difference
		 * is that dirty flag is unset for all the controller
		 * settings during cgroup creation, whereas some or all
		 * controller settings have the dirty flag set during
		 * modification.
		 *
		 * Be careful with ignore_non_dirty_values flag, setting
		 * it writing only the controller settings that has it
		 * dirty value set.
		 */
		if (ignore_non_dirty_values && cv->dirty == false)
			continue;

		/* We don't support, writing multiline settings */
		if (strcspn(cv->value, "\n")  < (strlen(cv->value) - 1))
			continue;

		ret = asprintf(&path, "%s%s", base, cv->name);
		if (ret < 0) {
			last_errno = errno;
			error = ECGOTHER;
			goto err;
		}

		/* skip read-only settings */
		ret = (stat(path, &path_stat));
		if (ret < 0) {
			last_errno = errno;
			error = ECGROUPVALUENOTEXIST;;
			goto err;
		}

		if (!(path_stat.st_mode & S_IWUSR))
			continue;

		cgroup_dbg("setting %s to \"%s\", pathlen %d\n", path, cv->value, ret);

		error = cg_set_control_value(path, cv->value);
		free(path);
		path = NULL;

		if (error) {
			/* Ignore the errors on deprecated settings */
			if (last_errno == EOPNOTSUPP) {
				error = 0;
				continue;
			}
			goto err;
		}
		cv->dirty = false;
	}

err:
	/*
	 * As currently written, path should always be null as we are\
	 * exiting this function, but let's check just in case, and free it
	 * if it's non-null
	 */
	if (path)
		free(path);

	return error;
}

/**
 * Check if the requested cgroup controller is enabled in the specified file
 *
 * @param path Cgroup directory
 * @param ctrl_name Name of the controller to check
 * @param output parameter that indicates whether the controller is enabled
 * @param file to open and parse
 * 	0 = cgroup.subtree_control
 * 	1 = cgroup.controllers
 */
STATIC int __cgroupv2_get_enabled(const char *path, const char *ctrl_name,
				  bool * const enabled, int file_enum)
{
	char *path_copy = NULL, *saveptr = NULL, *token, *ret_c, *filename;
	int ret, error = ECGROUPNOTMOUNTED;
	char buffer[FILENAME_MAX];
	FILE *fp = NULL;

	if (!path || !ctrl_name || !enabled)
		return ECGOTHER;

	*enabled = false;

	switch (file_enum) {
	case 0: /* cgroup.subtree_control */
		filename = CGV2_SUBTREE_CTRL_FILE;
		break;
	case 1: /* cgroup.controllers */
		filename = CGV2_CONTROLLERS_FILE;
		break;
	default:
		return ECGINVAL;
	}

	path_copy = (char *)malloc(FILENAME_MAX);
	if (!path_copy) {
		error = ECGOTHER;
		goto out;
	}

	ret = snprintf(path_copy, FILENAME_MAX, "%s/%s", path, filename);
	if (ret < 0) {
		error = ECGOTHER;
		goto out;
	}

	fp = fopen(path_copy, "re");
	if (!fp) {
		cgroup_warn("fopen failed\n");
		last_errno = errno;
		error = ECGOTHER;
		goto out;
	}

	ret_c = fgets(buffer, sizeof(buffer), fp);
	if (ret_c == NULL)
		/* The subtree control file is empty */
		goto out;

	/* Remove the trailing newline */
	ret_c[strlen(ret_c) - 1] = '\0';

	/*
	 * Split the enabled controllers by " " and evaluate if the
	 * requested controller is enabled.
	 */
	token = strtok_r(buffer, " ", &saveptr);
	do {
		if (strncmp(ctrl_name, token, FILENAME_MAX) == 0) {
			error = 0;
			*enabled = true;
			break;
		}
	} while ((token = strtok_r(NULL, " ", &saveptr)));

out:
	if (path_copy)
		free(path_copy);
	if (fp)
		fclose(fp);

	return error;
}

/**
 * Check if the requested cgroup controller is enabled on this subtree
 *
 * @param path Cgroup directory
 * @param ctrl_name Name of the controller to check
 * @param output parameter that indicates whether the controller is enabled
 */
STATIC int cgroupv2_get_subtree_control(const char *path, const char *ctrl_name,
					bool * const enabled)
{
	return __cgroupv2_get_enabled(path, ctrl_name, enabled, 0);
}
/**
 * Check if the requested cgroup controller is enabled in this cgroup's cgroup.controllers file
 *
 * @param path Cgroup directory
 * @param ctrl_name Name of the controller to check
 * @param output parameter that indicates whether the controller is enabled
 */
static int cgroupv2_get_controllers(const char *path, const char *ctrl_name,
				    bool * const enabled)
{
	return __cgroupv2_get_enabled(path, ctrl_name, enabled, 1);
}

/**
 * Enable/Disable a controller in the cgroup v2 subtree_control file
 *
 * @param path Directory that contains the subtree_control file
 * @param ctrl_name Name of the controller to be enabled/disabled
 * @param enable Enable/Disable the given controller
 */
STATIC int cgroupv2_subtree_control(const char *path, const char *ctrl_name, bool enable)
{
	int ret, error = ECGOTHER;
	char *path_copy = NULL;
	char *value = NULL;

	if (!path || !ctrl_name)
		return ECGOTHER;

	value = (char *)malloc(FILENAME_MAX);
	if (!value)
		goto out;

	path_copy = (char *)malloc(FILENAME_MAX);
	if (!path_copy)
		goto out;

	ret = snprintf(path_copy, FILENAME_MAX, "%s/%s", path, CGV2_SUBTREE_CTRL_FILE);
	if (ret < 0)
		goto out;

	if (enable)
		ret = snprintf(value, FILENAME_MAX, "+%s", ctrl_name);
	else
		ret = snprintf(value, FILENAME_MAX, "-%s", ctrl_name);
	if (ret < 0)
		goto out;

	error = cg_set_control_value(path_copy, value);
	if (error)
		goto out;

out:
	if (value)
		free(value);
	if (path_copy)
		free(path_copy);
	return error;
}

/**
 * Recursively enable/disable a controller in the cgv2 subtree_control file
 *
 * @param path Directory that contains the subtree_control file
 * @param ctrl_name Name of the controller to be enabled/disabled
 * @param enable Enable/Disable the given controller
 */
STATIC int cgroupv2_subtree_control_recursive(char *path, const char *ctrl_name, bool enable)
{
	char *path_copy, *tmp_path, *stok_buff = NULL;
	bool found_mount = false;
	size_t mount_len;
	int i, error = 0;

	for (i = 0; cg_mount_table[i].name[0] != '\0'; i++) {
		if (strncmp(cg_mount_table[i].name, ctrl_name,
			    sizeof(cg_mount_table[i].name)) == 0) {
			found_mount = true;
			break;
		}
	}

	if (!found_mount)
		return ECGROUPSUBSYSNOTMOUNTED;

	path_copy = strdup(path);
	if (!path_copy)
		return ECGOTHER;

	/*
	 * Null terminate the path_copy to match the string length of the
	 * controller mount.  We'll incrementally build up the string, subdir
	 * by subdir, and enable the subtree control file each step of the way
	 */
	mount_len = strlen(cg_mount_table[i].mount.path);
	path_copy[mount_len] = '\0';

	tmp_path = strtok_r(&path[mount_len], "/", &stok_buff);
	do {
		if (tmp_path) {
			strcat(path_copy, "/");
			strcat(path_copy, tmp_path);
		}

		error = cg_create_control_group(path_copy);
		if (error)
			goto out;

		error = cgroupv2_subtree_control(path_copy, ctrl_name, enable);
		if (error)
			goto out;
	} while ((tmp_path = strtok_r(NULL, "/", &stok_buff)));

out:
	free(path_copy);
	return error;
}

/**
 * cgroup_modify_cgroup modifies the cgroup control files.
 * struct cgroup *cgroup: The name will be the cgroup to be modified.
 * The values will be the values to be modified, those not mentioned in the
 * structure will not be modified.
 *
 * The uids cannot be modified yet.
 *
 * returns 0 on success.
 */

int cgroup_modify_cgroup(struct cgroup *cgroup)
{
	char base[FILENAME_MAX];
	int error = 0;
	int i;

	if (!cgroup_initialized)
		return ECGROUPNOTINITIALIZED;

	if (!cgroup)
		return ECGROUPNOTALLOWED;

	for (i = 0; i < cgroup->index; i++) {
		if (!cgroup_test_subsys_mounted(cgroup->controller[i]->name)) {
			cgroup_warn("subsystem %s is not mounted\n", cgroup->controller[i]->name);
			return ECGROUPSUBSYSNOTMOUNTED;
		}
	}

	for (i = 0; i < cgroup->index; i++) {
		if (!cg_build_path(cgroup->name, base, cgroup->controller[i]->name))
			continue;

		error = cgroup_set_values_recursive(base, cgroup->controller[i], true);
		if (error)
			goto err;
	}
err:
	return error;
}

int cgroup_copy_controller_values(struct cgroup_controller * const dst,
				  const struct cgroup_controller * const src)
{
	int i, ret = 0;

	if (!dst || !src)
		return ECGFAIL;

	strncpy(dst->name, src->name, CONTROL_NAMELEN_MAX);
	for (i = 0; i < src->index; i++, dst->index++) {
		struct control_value *src_val = src->values[i];
		struct control_value *dst_val;

		dst->values[i] = calloc(1, sizeof(struct control_value));
		if (!dst->values[i]) {
			last_errno = errno;
			ret = ECGOTHER;
			goto err;
		}

		dst_val = dst->values[i];
		strncpy(dst_val->value, src_val->value, CG_CONTROL_VALUE_MAX);
		strncpy(dst_val->name, src_val->name, FILENAME_MAX);

		if (src_val->multiline_value) {
			dst_val->multiline_value = strdup(src_val->multiline_value);
			if (!dst_val->multiline_value) {
				last_errno = errno;
				ret = ECGOTHER;
				goto err;
			}
		} else {
			dst_val->multiline_value = NULL;
		}

		if (src_val->prev_name) {
			dst_val->prev_name = strdup(src_val->prev_name);
			if (!dst_val->prev_name) {
				last_errno = errno;
				ret = ECGOTHER;
				goto err;
			}
		} else {
			dst_val->prev_name = NULL;
		}
		/*
		 * set dirty flag unconditionally, as we overwrite
		 * destination controller values.
		 */
		dst_val->dirty = true;
	}

	return ret;

err:
	dst->index = 0;
	for (i = 0; i < src->index; i++) {
		if (dst->values[i]) {
			if (dst->values[i]->multiline_value)
				free(dst->values[i]->multiline_value);

			if (dst->values[i]->prev_name)
				free(dst->values[i]->prev_name);

			free(dst->values[i]);
		}
	}

	return ret;
}

/**
 * @dst: Destination control group
 * @src: Source from which values will be copied to dst
 *
 * Create a duplicate copy of src in dst. This will be useful for those who
 * that intend to create new instances based on an existing control group
 */
int cgroup_copy_cgroup(struct cgroup *dst, struct cgroup *src)
{
	int ret = 0, i;

	if (!dst || !src)
		return ECGROUPNOTEXIST;

	/* Should we just use the restrict keyword instead? */
	if (dst == src)
		return ECGFAIL;

	cgroup_free_controllers(dst);

	for (i = 0; i < src->index; i++, dst->index++) {
		struct cgroup_controller *src_ctlr = src->controller[i];
		struct cgroup_controller *dst_ctlr;

		dst->controller[i] = calloc(1, sizeof(struct cgroup_controller));
		if (!dst->controller[i]) {
			last_errno = errno;
			ret = ECGOTHER;
			goto err;
		}

		dst_ctlr = dst->controller[i];
		ret = cgroup_copy_controller_values(dst_ctlr, src_ctlr);
		if (ret)
			goto err;
	}
err:
	return ret;
}

/**
 * Chown and chmod the tasks file in cg_path
 *
 * @param uid The UID that will own the tasks file
 * @param gid The GID that will own the tasks file
 * @param fperm The permissions to place on the tasks file
 */
STATIC int cgroup_chown_chmod_tasks(const char * const cg_path, uid_t uid, gid_t gid, mode_t fperm)
{
	int ret, error;
	char *tasks_path = NULL;

	tasks_path = (char *)malloc(FILENAME_MAX);
	if (tasks_path == NULL)
		return ECGOTHER;

	ret = snprintf(tasks_path, FILENAME_MAX, "%s/tasks", cg_path);
	if (ret < 0 || ret >= FILENAME_MAX) {
		last_errno = errno;
		error = ECGOTHER;
		goto err;
	}

	error = cg_chown(tasks_path, uid, gid);
	if (!error && fperm != NO_PERMS)
		error = cg_chmod_path(tasks_path, fperm, 1);

	if (error) {
		last_errno = errno;
		error = ECGOTHER;
	}

err:
	if (tasks_path)
		free(tasks_path);

	return error;
}

static int _cgroup_create_cgroup(const struct cgroup * const cgroup,
				 const struct cgroup_controller * const controller,
				 int ignore_ownership)
{
	enum cg_version_t version = CGROUP_UNK;
	char *fts_path[2];
	char *base = NULL;
	char *path = NULL;
	int error;

	fts_path[0] = (char *)malloc(FILENAME_MAX);
	if (!fts_path[0]) {
		last_errno = errno;
		return ECGOTHER;
	}
	fts_path[1] = NULL;
	path = fts_path[0];

	if (controller) {
		if (!cg_build_path(cgroup->name, path, controller->name)) {
			error = ECGOTHER;
			goto err;
		}

		error = cgroup_get_controller_version(controller->name, &version);
		if (error)
			goto err;

		if (version == CGROUP_V2) {
			char *parent, *dname;

			parent = strdup(path);
			if (!parent) {
				error = ECGOTHER;
				goto err;
			}

			dname = dirname(parent);

			error = cgroupv2_subtree_control_recursive(dname, controller->name, true);
			free(parent);
			if (error)
				goto err;
		}
	} else {
		if (!cg_build_path(cgroup->name, path, NULL)) {
			error = ECGOTHER;
			goto err;
		}
	}

	error = cg_create_control_group(path);
	if (error)
		goto err;

	base = strdup(path);

	if (!base) {
		last_errno = errno;
		error = ECGOTHER;
		goto err;
	}

	if (!ignore_ownership) {
		cgroup_dbg("Changing ownership of %s\n", fts_path[0]);
		error = cg_chown_recursive(fts_path, cgroup->control_uid, cgroup->control_gid);
		if (!error)
			error = cg_chmod_recursive_controller(fts_path[0],
							      cgroup->control_dperm,
							      cgroup->control_dperm != NO_PERMS,
							      cgroup->control_fperm,
							      cgroup->control_fperm != NO_PERMS,
							      1, cgroup_ignored_tasks_files);
	}

	if (error)
		goto err;

	if (controller) {
		error = cgroup_set_values_recursive(base, controller, false);
		if (error)
			goto err;
	}

	if (!ignore_ownership && version == CGROUP_V1) {
		error = cgroup_chown_chmod_tasks(base, cgroup->tasks_uid, cgroup->tasks_gid,
						 cgroup->task_fperm);
		if (error)
			goto err;
	}
err:
	if (path)
		free(path);
	if (base)
		free(base);

	return error;
}

/**
 * cgroup_create_cgroup creates a new control group.
 * struct cgroup *cgroup: The control group to be created
 *
 * returns 0 on success. We recommend calling cg_delete_cgroup if this
 * routine fails. That should do the cleanup operation. If ECGCANTSETVALUE
 * is returned, the group was created successfully but not all controller
 * parameters were successfully set.
 */
int cgroup_create_cgroup(struct cgroup *cgroup, int ignore_ownership)
{
	int error = 0;
	int i;

	if (!cgroup_initialized)
		return ECGROUPNOTINITIALIZED;

	if (!cgroup)
		return ECGROUPNOTALLOWED;

	for (i = 0; i < cgroup->index;	i++) {
		if (!cgroup_test_subsys_mounted(cgroup->controller[i]->name))
			return ECGROUPSUBSYSNOTMOUNTED;
	}

	if (cgroup->index == 0) {
		/* Create an empty cgroup v2 cgroup */
		error = _cgroup_create_cgroup(cgroup, NULL, ignore_ownership);
		if (error)
			/*
			 * Since we only attempted to create a single cgroup,
			 * _cgroup_create_cgroup() can capably undo the failure and no
			 * interventions are required here.
			 */
			return error;
	}

	/*
	 * XX: One important test to be done is to check, if you have
	 * multiple subsystems mounted at one point, all of them *have* be
	 * on the cgroup data structure. If not, we fail.
	 */
	for (i = 0; i < cgroup->index; i++) {
		error = _cgroup_create_cgroup(cgroup, cgroup->controller[i], ignore_ownership);
		if (error) {
			int del_error;

			/*
			 * This will remove any cgroup directories that were made, but it won't
			 * undo changes that have been written to the parent cgroup's
			 * subtree_control file.  To safely undo changes there, we would need to
			 * save the subtree_control file's previous value and restore it.
			 */
			del_error = cgroup_delete_cgroup(cgroup, 1);
			if (del_error)
				cgroup_err("Failed to delete %s: %s\n", cgroup->name,
					   cgroup_strerror(del_error));
			return error;
		}
	}

	return 0;
}

/**
 * Obtain the calculated parent name of specified cgroup; no validation of
 * the existence of the child or parent group is performed.
 *
 * Given the path-like hierarchy of cgroup names, this function returns the
 * dirname() of the cgroup name as the likely parent name; the caller is
 * responsible for validating parent as appropriate.
 *
 * @param cgroup The cgroup to query for parent's name
 * @param parent Output, name of parent's group, or NULL if the
 *	provided cgroup is the root group.
 *	Caller is responsible to free the returned string.
 * @return 0 on success, > 0 on error
 */
static int cgroup_get_parent_name(struct cgroup *cgroup, char **parent)
{
	char *pdir = NULL;
	char *dir = NULL;
	int ret = 0;

	dir = strdup(cgroup->name);
	if (!dir) {
		last_errno = errno;
		return ECGOTHER;
	}
	cgroup_dbg("group name is %s\n", dir);

	pdir = dirname(dir);
	cgroup_dbg("parent's group name is %s\n", pdir);

	/* Check for root group */
	if (strlen(cgroup->name) == 0 || !strcmp(cgroup->name, pdir)) {
		cgroup_dbg("specified cgroup \"%s\" is root group\n", cgroup->name);
		*parent = NULL;
	} else {
		*parent = strdup(pdir);
		if (*parent == NULL) {
			last_errno = errno;
			ret = ECGOTHER;
		}
	}
	free(dir);

	return ret;
}

/*
 * Checks if the cgroup's controller shares the mount point with any other
 * controller in cg_mount_table.
 * Returns 1 if shared or 0.
 */
static int is_cgrp_ctrl_shared_mnt(char *controller)
{
	int ret = 0;
	int i;

	if (!controller)
		return ret;

	pthread_rwlock_rdlock(&cg_mount_table_lock);

	for (i = 0; cg_mount_table[i].name[0] != '\0'; i++) {

		if (strncmp(cg_mount_table[i].name, controller, CONTROL_NAMELEN_MAX) == 0 &&
		    cg_mount_table[i].shared_mnt) {
			ret = 1;
			break;
		}
	}

	pthread_rwlock_unlock(&cg_mount_table_lock);

	return ret;
}
/**
 * Find the parent of the specified directory. It returns the parent in
 * hierarchy of given controller (the parent is usually name/.. unless name
 * is a mount point.  It is assumed both the cgroup (and, therefore, parent)
 * already exist, and will fail otherwise.
 *
 * When namespaces are used, a group can have different parents for
 * different controllers.
 *
 * @param cgroup The cgroup
 * @param controller The controller
 * @param parent Output, name of parent's group (if the group has parent) or
 *	NULL, if the provided cgroup is the root group and has no parent.
 *	Caller is responsible to free the returned string!
 * @return 0 on success, >0 on error.
 */
static int cgroup_find_parent(struct cgroup *cgroup, char *controller, char **parent)
{
	struct stat stat_child, stat_parent;
	char child_path[FILENAME_MAX];
	char *parent_path = NULL;
	int ret = 0;

	*parent = NULL;

	pthread_rwlock_rdlock(&cg_mount_table_lock);
	if (!cg_build_path_locked(cgroup->name, child_path, controller)) {
		pthread_rwlock_unlock(&cg_mount_table_lock);
		return ECGFAIL;
	}
	pthread_rwlock_unlock(&cg_mount_table_lock);

	cgroup_dbg("path is %s\n", child_path);

	if (asprintf(&parent_path, "%s/..", child_path) < 0)
		return ECGFAIL;

	cgroup_dbg("parent's name is %s\n", parent_path);

	if (stat(child_path, &stat_child) < 0) {
		if (is_cgrp_ctrl_shared_mnt(controller)) {
			last_errno = errno;
			ret = ECGROUPNOTEXIST;
			goto free_parent;
		}
		last_errno = errno;
		ret = ECGOTHER;
		goto free_parent;
	}

	if (stat(parent_path, &stat_parent) < 0) {
		last_errno = errno;
		ret = ECGOTHER;
		goto free_parent;
	}

	/* Is the specified "name" a mount point? */
	if (stat_parent.st_dev != stat_child.st_dev) {
		*parent = NULL;
		ret = 0;
		cgroup_dbg("Parent is on different device\n");
	} else {
		ret = cgroup_get_parent_name(cgroup, parent);
	}

free_parent:
	free(parent_path);
	return ret;
}

/**
 * @cgroup: cgroup data structure to be filled with parent values and then
 *	passed down for creation
 * @ignore_ownership: Ignore doing a chown on the newly created cgroup
 * @return 0 on success, > 0 on failure.  If  ECGCANTSETVALUE is returned,
 *	the group was created
 * successfully, but not all controller parameters were copied from the
 * parent successfully; unfortunately, this is expected...
 */
int cgroup_create_cgroup_from_parent(struct cgroup *cgroup, int ignore_ownership)
{
	struct cgroup *parent_cgroup = NULL;
	char *parent = NULL;
	int ret = ECGFAIL;

	if (!cgroup_initialized)
		return ECGROUPNOTINITIALIZED;

	ret = cgroup_get_parent_name(cgroup, &parent);
	if (ret)
		return ret;

	if (parent == NULL) {
		/*
		 * The group to create is root group!
		 * TODO: find better error code?
		 */
		return ECGFAIL;
	}

	cgroup_dbg("parent is %s\n", parent);
	parent_cgroup = cgroup_new_cgroup(parent);
	if (!parent_cgroup) {
		ret = ECGFAIL;
		goto err_nomem;
	}

	if (cgroup_get_cgroup(parent_cgroup)) {
		ret = ECGFAIL;
		goto err_parent;
	}

	cgroup_dbg("got parent group for %s\n", parent_cgroup->name);
	ret = cgroup_copy_cgroup(cgroup, parent_cgroup);
	if (ret)
		goto err_parent;

	cgroup_dbg("copied parent group %s to %s\n", parent_cgroup->name, cgroup->name);
	ret = cgroup_create_cgroup(cgroup, ignore_ownership);

err_parent:
	cgroup_free(&parent_cgroup);
err_nomem:
	free(parent);
	return ret;
}

/**
 * Move all processes from one task file to another.
 * @param input_tasks Pre-opened file to read tasks from.
 * @param output_tasks Pre-opened file to write tasks to.
 * @return 0 on succes, >0 on error.
 */
static int cg_move_task_files(FILE *input_tasks, FILE *output_tasks)
{
	int ret = 0;
	int tids;

	while (!feof(input_tasks)) {
		ret = fscanf(input_tasks, "%d", &tids);
		if (ret == EOF || ret == 0) {
			ret = 0;
			break;
		}
		if (ret < 0)
			break;

		ret = fprintf(output_tasks, "%d", tids);
		if (ret < 0 && errno != ESRCH)
			break;

		/* Flush the file, we need only one process per write() call. */
		ret = fflush(output_tasks);
		if (ret < 0) {
			if (errno == ESRCH)
				ret = 0;
			else
				break;
		}
	}

	if (ret < 0) {
		last_errno = errno;
		return ECGOTHER;
	}
	return 0;
}

/**
 * Remove one cgroup from specific controller. The function moves all
 * processes from it to given target group.
 *
 * The function succeeds if the group to remove is already removed - when
 * cgroup_delete_cgroup is called with group with two controllers mounted
 * to the same hierarchy, this function is called once for each of these
 * controllers. And during the second call the group is already removed...
 *
 * @param cgroup_name Name of the group to remove.
 * @param controller  Name of the controller.
 * @param target_tasks Opened tasks file of the target group, where all
 *	processes should be moved.
 * @param flags Flag indicating whether the errors from task
 *	migration should be ignored (CGROUP_DELETE_IGNORE_MIGRATION) or not (0).
 * @returns 0 on success, >0 on error.
 */
static int cg_delete_cgroup_controller(char *cgroup_name, char *controller, FILE *target_tasks,
				       int flags)
{
	char path[FILENAME_MAX];
	FILE *delete_tasks;
	int ret = 0;

	cgroup_dbg("Removing group %s:%s\n", controller, cgroup_name);

	if (!(flags & CGFLAG_DELETE_EMPTY_ONLY)) {
		/* Open tasks file of the group to delete. */
		ret = cgroup_build_tasks_procs_path(path, sizeof(path), cgroup_name, controller);
		if (ret != 0)
			return ECGROUPSUBSYSNOTMOUNTED;

		delete_tasks = fopen(path, "re");
		if (delete_tasks) {
			ret = cg_move_task_files(delete_tasks, target_tasks);
			if (ret != 0) {
				cgroup_warn("removing tasks from %s failed: %s\n", path,
					    cgroup_strerror(ret));
			}
			fclose(delete_tasks);
		} else {
			/*
			 * Can't open the tasks file. If the file does not exist,
			 * ignore it - the group has been already removed.
			 */
			if (errno != ENOENT) {
				cgroup_err("cannot open %s: %s\n", path, strerror(errno));
				last_errno = errno;
				ret = ECGOTHER;
			}
		}

		if (ret != 0 && !(flags & CGFLAG_DELETE_IGNORE_MIGRATION))
			return ret;
	}

	/* Remove the group. */
	if (!cg_build_path(cgroup_name, path, controller))
		return ECGROUPSUBSYSNOTMOUNTED;

	ret = rmdir(path);
	if (ret == 0 || errno == ENOENT)
		return 0;

	if ((flags & CGFLAG_DELETE_EMPTY_ONLY) && (errno == EBUSY))
		return ECGNONEMPTY;

	cgroup_warn("cannot remove directory %s: %s\n", path, strerror(errno));
	last_errno = errno;

	return ECGOTHER;
}

/**
 * Recursively delete one control group. Moves all tasks from the group and
 * its subgroups to given task file.
 *
 * @param cgroup_name The group to delete.
 * @param controller The controller, where to delete.
 * @param target_tasks Opened file, where all tasks should be moved.
 * @param flags Combination of CGFLAG_DELETE_* flags. The function assumes
 *	that CGFLAG_DELETE_RECURSIVE is set.
 * @param delete_root Whether the group itself should be removed(1) or not(0).
 */
static int cg_delete_cgroup_controller_recursive(char *cgroup_name, char *controller,
						 FILE *target_tasks, int flags, int delete_root)
{
	char child_name[FILENAME_MAX + 1];
	struct cgroup_file_info info;
	int level, group_len;
	void *handle;
	int ret;

	cgroup_dbg("Recursively removing %s:%s\n", controller, cgroup_name);

	ret = cgroup_walk_tree_begin(controller, cgroup_name, 0, &handle, &info, &level);

	if (ret == 0)
		ret = cgroup_walk_tree_set_flags(&handle, CGROUP_WALK_TYPE_POST_DIR);

	if (ret != 0) {
		cgroup_walk_tree_end(&handle);
		return ret;
	}

	group_len = strlen(info.full_path);

	/* Skip the root group, it will be handled explicitly at the end. */
	ret = cgroup_walk_tree_next(0, &handle, &info, level);

	while (ret == 0) {
		if (info.type == CGROUP_FILE_TYPE_DIR && info.depth > 0) {
			snprintf(child_name, sizeof(child_name), "%s/%s", cgroup_name,
				 info.full_path + group_len);

			ret = cg_delete_cgroup_controller(child_name, controller, target_tasks,
							  flags);
			if (ret != 0)
				break;
		}

		ret = cgroup_walk_tree_next(0, &handle, &info, level);
	}
	if (ret == ECGEOF) {
		/* Iteration finished successfully, remove the root group. */
		ret = 0;
		if (delete_root)
			ret = cg_delete_cgroup_controller(cgroup_name, controller, target_tasks,
							  flags);
	}

	cgroup_walk_tree_end(&handle);

	return ret;
}

/**
 * cgroup_delete cgroup deletes a control group.
 * struct cgroup *cgroup takes the group which is to be deleted.
 *
 * returns 0 on success.
 */
int cgroup_delete_cgroup(struct cgroup *cgroup, int ignore_migration)
{
	int flags = ignore_migration ? CGFLAG_DELETE_IGNORE_MIGRATION : 0;

	return cgroup_delete_cgroup_ext(cgroup, flags);
}

int cgroup_delete_cgroup_ext(struct cgroup *cgroup, int flags)
{
	int first_error = 0, first_errno = 0;
	int cgrp_del_on_shared_mnt = 0;
	char parent_path[FILENAME_MAX];
	char *controller_name = NULL;
	FILE *parent_tasks = NULL;
	char *parent_name = NULL;
	int delete_group = 1;
	int empty_cgroup = 0;
	int i, ret;

	if (!cgroup_initialized)
		return ECGROUPNOTINITIALIZED;

	if (!cgroup)
		return ECGROUPNOTALLOWED;

	if ((flags & CGFLAG_DELETE_RECURSIVE)
	    && (flags & CGFLAG_DELETE_EMPTY_ONLY))
		return ECGINVAL;

	if (cgroup->index == 0)
		/* Valid empty cgroup v2 with not controllers added. */
		empty_cgroup = 1;

	for (i = 0; i < cgroup->index; i++) {
		if (!cgroup_test_subsys_mounted(cgroup->controller[i]->name))
			return ECGROUPSUBSYSNOTMOUNTED;
	}

	/*
	 * Remove the group from all controllers and in the case of cgroup
	 * with no controllers, perform all actions of a single controller.
	 */
	for (i = 0;
	     empty_cgroup > 0 || i < cgroup->index; i++, empty_cgroup--) {

		ret = 0;
		controller_name = NULL;

		if (cgroup->controller[i])
			controller_name = cgroup->controller[i]->name;

		/* Find parent, it can be different for each controller */
		if (!(flags & CGFLAG_DELETE_EMPTY_ONLY)) {
			ret = cgroup_find_parent(cgroup, controller_name, &parent_name);
			if (ret) {
				/*
				 * ECGROPNOTEXIST is returned on cgroup v1, where
				 * controllers share the mount points. When cgroup
				 * is deleted on one of symbolic link (controller)
				 * and they should pass on other controllers sharing
				 * the mount point.
				 *
				 * cgrp_del_shared_mnt serves as an extra check
				 * flag that gets set, when the cgroup exists and
				 * is deleted or else sets an error.
				 */
				if (first_error == 0 &&
				    (ret != ECGROUPNOTEXIST ||
				    (ret == ECGROUPNOTEXIST && cgrp_del_on_shared_mnt == 0))) {
						first_errno = last_errno;
						first_error = ECGOTHER;
				}
				continue;
			}

			if (is_cgrp_ctrl_shared_mnt(controller_name))
					cgrp_del_on_shared_mnt = 1;

			if (parent_name == NULL) {
				/* Root group is being deleted. */
				if (!(flags & CGFLAG_DELETE_RECURSIVE))
					/* root group is being deleted in non-recursive mode */
					continue;
				/*
				 * Move all tasks to the root group and
				 * do not delete it afterwards.
				 */
				parent_name = strdup(".");
				if (parent_name == NULL) {
					if (first_error == 0) {
						first_errno = errno;
						first_error = ECGOTHER;
					}
					continue;
				}
				delete_group = 0;
			}
		}

		if (parent_name) {
			/* Tasks need to be moved, pre-open target tasks file */
			ret = cgroup_build_tasks_procs_path(parent_path, sizeof(parent_path),
							    parent_name, controller_name);
			if (ret != 0) {
				if (first_error == 0)
					first_error = ECGFAIL;
				free(parent_name);
				continue;
			}

			parent_tasks = fopen(parent_path, "we");
			if (!parent_tasks) {
				if (first_error == 0) {
					cgroup_warn("cannot open tasks file %s: %s\n", parent_path,
						    strerror(errno));
					first_errno = errno;
					first_error = ECGOTHER;
				}
				free(parent_name);
				continue;
			}
		}
		if (flags & CGFLAG_DELETE_RECURSIVE) {
			ret = cg_delete_cgroup_controller_recursive(cgroup->name, controller_name,
								    parent_tasks, flags,
								    delete_group);
		} else {
			ret = cg_delete_cgroup_controller(cgroup->name, controller_name,
							  parent_tasks, flags);
		}

		if (parent_tasks) {
			fclose(parent_tasks);
			parent_tasks = NULL;
		}
		free(parent_name);
		parent_name = NULL;
		/*
		 * If any of the controller delete fails, remember the first
		 * error code, but continue with next controller and try remove
		 * the group from all of them.
		 */
		if (ret) {
			/*
			 * ECGNONEMPTY is more or less not an error, but an
			 * indication that something was not removed.
			 * Therefore it should be replaced by any other error.
			 */
			if (ret != ECGNONEMPTY &&
			    (first_error == 0 || first_errno == ECGNONEMPTY)) {
				first_errno = last_errno;
				first_error = ret;
			}
		}
	}

	/*
	 * Restore the last_errno to the first errno from
	 * cg_delete_cgroup_controller[_ext].
	 */
	if (first_errno != 0)
		last_errno = first_errno;

	return first_error;
}

/*
 * This function should really have more checks, but this version will assume
 * that the callers have taken care of everything. Including the locking.
 */
static int cg_rd_ctrl_file(const char *subsys, const char *cgroup, const char *file, char **value)
{
	char path[FILENAME_MAX];
	FILE *ctrl_file = NULL;
	int ret;

	if (!cg_build_path_locked(cgroup, path, subsys))
		return ECGFAIL;

	strncat(path, file, sizeof(path) - strlen(path));
	ctrl_file = fopen(path, "re");
	if (!ctrl_file)
		return ECGROUPVALUENOTEXIST;

	*value = calloc(CG_CONTROL_VALUE_MAX, 1);
	if (!*value) {
		fclose(ctrl_file);
		last_errno = errno;
		return ECGOTHER;
	}

	/* Using %as crashes when we try to read from files like memory.stat */
	ret = fread(*value, 1, CG_CONTROL_VALUE_MAX-1, ctrl_file);
	if (ret < 0) {
		free(*value);
		*value = NULL;
	} else {
		/* Remove trailing \n */
		if (ret > 0 && (*value)[ret-1] == '\n')
			(*value)[ret-1] = '\0';
	}

	fclose(ctrl_file);

	return 0;
}

/*
 * Call this function with required locks taken.
 */
int cgroup_fill_cgc(struct dirent *ctrl_dir, struct cgroup *cgroup, struct cgroup_controller *cgc,
		    int cg_index)
{
	char path[FILENAME_MAX+1];
	struct stat stat_buffer;
	char *ctrl_value = NULL;
	char *ctrl_name = NULL;
	char *ctrl_file = NULL;
	char *tmp_path = NULL;
	char *d_name = NULL;
	char *buffer = NULL;
	int tmp_len = 0;
	int error = 0;

	d_name = strdup(ctrl_dir->d_name);

	if (!strcmp(d_name, ".") || !strcmp(d_name, "..")) {
		error = ECGINVAL;
		goto fill_error;
	}

	/*
	 * This part really needs to be optimized out. Probably use some
	 * sort of a flag, but this is fine for now.
	 */
	cg_build_path_locked(cgroup->name, path, cg_mount_table[cg_index].name);
	strncat(path, d_name, sizeof(path) - strlen(path));

	error = stat(path, &stat_buffer);

	if (error) {
		error = ECGFAIL;
		goto fill_error;
	}

	/*
	 * We have already stored the tasks_uid & tasks_gid. This check is
	 * to avoid the overwriting of the values stored in
	 * control_uid & cotrol_gid. tasks file will have the uid and gid of
	 * the user who is capable of putting a task to this cgroup.
	 * control_uid and control_gid is meant for the users who are capable
	 * of managing the cgroup shares.
	 *
	 * The strstr() function will return the pointer to the
	 * beginning of the sub string "/tasks".
	 */
	tmp_len = strlen(path) - strlen("/tasks");

	/* tmp_path would be pointing to the last six characters */
	tmp_path = (char *)path + tmp_len;

	/*
	 * Checking to see, if this is actually a 'tasks' file We need to
	 * compare the last 6 bytes
	 */
	if (strcmp(tmp_path, "/tasks")) {
		cgroup->control_uid = stat_buffer.st_uid;
		cgroup->control_gid = stat_buffer.st_gid;
	}

	ctrl_name = strtok_r(d_name, ".", &buffer);

	if (!ctrl_name) {
		error = ECGFAIL;
		goto fill_error;
	}

	ctrl_file = strtok_r(NULL, ".", &buffer);

	if (!ctrl_file) {
		error = ECGINVAL;
		goto fill_error;
	}

	if (strcmp(ctrl_name, cg_mount_table[cg_index].name) == 0) {
		error = cg_rd_ctrl_file(cg_mount_table[cg_index].name, cgroup->name,
					ctrl_dir->d_name, &ctrl_value);
		if (error || !ctrl_value)
			goto fill_error;

		if (cgroup_add_value_string(cgc, ctrl_dir->d_name, ctrl_value)) {
			error = ECGFAIL;
			goto fill_error;
		}
	}
fill_error:
	if (ctrl_value)
		free(ctrl_value);
	free(d_name);

	return error;
}

/*
 * cgroup_get_cgroup reads the cgroup data from the filesystem.
 * struct cgroup has the name of the group to be populated
 *
 * return 0 on success.
 */
int cgroup_get_cgroup(struct cgroup *cgroup)
{
	char cgrp_ctrl_path[FILENAME_MAX];
	struct dirent *ctrl_dir = NULL;
	char mnt_path[FILENAME_MAX];
	int initial_controller_cnt;
	char *control_path = NULL;
	int controller_cnt = 0;
	DIR *dir = NULL;
	int error;
	int i, j;
	int ret;

	if (!cgroup_initialized) {
		/* ECGROUPNOTINITIALIZED */
		return ECGROUPNOTINITIALIZED;
	}

	if (!cgroup) {
		/* ECGROUPNOTALLOWED */
		return ECGROUPNOTALLOWED;
	}

	initial_controller_cnt = cgroup->index;

	pthread_rwlock_rdlock(&cg_mount_table_lock);
	for (i = 0; i < CG_CONTROLLER_MAX && cg_mount_table[i].name[0] != '\0'; i++) {
		struct cgroup_controller *cgc;
		struct stat stat_buffer;
		int mnt_path_len;

		if (initial_controller_cnt > 0) {
			bool skip_this_controller = true;

			/*
			 * The user has specified a list of controllers they are interested
			 * in.  Only operate on the specified controllers
			 */
			for (j = 0; j < cgroup->index; j++) {
				if (strncmp(cg_mount_table[i].name, cgroup->controller[j]->name,
					    CONTROL_NAMELEN_MAX) == 0)
					skip_this_controller = false;
			}

			if (skip_this_controller)
				continue;
		}

		if (!cg_build_path_locked(NULL, mnt_path, cg_mount_table[i].name))
			continue;

		mnt_path_len = strlen(mnt_path);
		strncat(mnt_path, cgroup->name, FILENAME_MAX - mnt_path_len - 1);
		mnt_path[sizeof(mnt_path) - 1] = '\0';

		if (access(mnt_path, F_OK))
			continue;

		if (!cg_build_path_locked(cgroup->name, cgrp_ctrl_path, cg_mount_table[i].name)) {
			/* This fails when the cgroup does not exist for that controller. */
			continue;
		}

		/* Get the uid and gid information. */
		if (cg_mount_table[i].version == CGROUP_V1) {
			ret = asprintf(&control_path, "%s/tasks", cgrp_ctrl_path);

			if (ret < 0) {
				last_errno = errno;
				error = ECGOTHER;
				goto unlock_error;
			}

			if (stat(control_path, &stat_buffer)) {
				last_errno = errno;
				free(control_path);
				error = ECGOTHER;
				goto unlock_error;
			}

			cgroup->tasks_uid = stat_buffer.st_uid;
			cgroup->tasks_gid = stat_buffer.st_gid;

			free(control_path);
		} else { /* cgroup v2 */
			bool enabled;

			error = cgroupv2_get_controllers(cgrp_ctrl_path, cg_mount_table[i].name,
							 &enabled);
			if (error == ECGROUPNOTMOUNTED) {
				/*
				 * This controller isn't enabled.  Only hide it from the
				 * user if they've chosen to view all enabled controllers.
				 *
				 * If they've specified the controllers they're interested in
				 * and we've made it this far, then they are explicitly
				 * interested in this controller and we should not remove it.
				 */
				if (initial_controller_cnt == 0) {
					controller_cnt++;
					continue;
				}
			} else if (error) {
				goto unlock_error;
			}
		}

		if (initial_controller_cnt)
			cgc = cgroup_get_controller(cgroup, cg_mount_table[i].name);
		else
			cgc = cgroup_add_controller(cgroup, cg_mount_table[i].name);
		if (!cgc) {
			error = ECGINVAL;
			goto unlock_error;
		}

		dir = opendir(cgrp_ctrl_path);
		if (!dir) {
			last_errno = errno;
			error = ECGOTHER;
			goto unlock_error;
		}

		controller_cnt++;

		while ((ctrl_dir = readdir(dir)) != NULL) {
			/* Skip over non regular files */
			if (ctrl_dir->d_type != DT_REG)
				continue;

			error = cgroup_fill_cgc(ctrl_dir, cgroup, cgc, i);
			for (j = 0; j < cgc->index; j++)
				cgc->values[j]->dirty = false;

			if (error == ECGFAIL) {
				closedir(dir);
				goto unlock_error;
			}
		}
		closedir(dir);

		if (!strcmp(cgc->name, "memory")) {
			/*
			 * Make sure that memory.limit_in_bytes is placed before
			 * memory.memsw.limit_in_bytes in the list of values
			 */
			int memsw_limit = -1;
			int mem_limit = -1;

			for (j = 0; j < cgc->index; j++) {
				if (!strcmp(cgc->values[j]->name, "memory.memsw.limit_in_bytes"))
					memsw_limit = j;
				else if (!strcmp(cgc->values[j]->name, "memory.limit_in_bytes"))
					mem_limit = j;
			}

			if (memsw_limit >= 0 && memsw_limit < mem_limit) {
				struct control_value *val = cgc->values[memsw_limit];

				cgc->values[memsw_limit] = cgc->values[mem_limit];
				cgc->values[mem_limit] = val;
			}
		}
	}

	/*
	 * Check if the group really exists or not.  The cgroup->index controller count can't
	 * be used in this case because cgroup v2 allows controllers to be enabled/disabled in
	 * the subtree_control file.  Rather, cgroup_get_cgroup() tracks the number of possible
	 * controllers in the controller_cnt variable and uses that to determine if the cgroup
	 * exists or not.
	 */
	if (!controller_cnt) {
		error = ECGROUPNOTEXIST;
		goto unlock_error;
	}

	pthread_rwlock_unlock(&cg_mount_table_lock);

	return 0;

unlock_error:
	pthread_rwlock_unlock(&cg_mount_table_lock);
	/*
	 * XX: Need to figure out how to cleanup? Cleanup just the stuff
	 * we added, or the whole structure.
	 */
	cgroup_free_controllers(cgroup);
	cgroup = NULL;

	return error;
}

/**
 * cg_prepare_cgroup Process the selected rule. Prepare the cgroup structure
 * which can be used to add the task to destination cgroup.
 *
 *  returns 0 on success.
 */
static int cg_prepare_cgroup(struct cgroup *cgroup, pid_t pid, const char *dest,
			     const char * const controllers[])
{
	struct cgroup_controller *cptr = NULL;
	const char *controller = NULL;
	int ret = 0, i;

	/* Fill in cgroup details.  */
	cgroup_dbg("Will move pid %d to cgroup '%s'\n", pid, dest);

	strncpy(cgroup->name, dest, FILENAME_MAX);
	cgroup->name[FILENAME_MAX-1] = '\0';

	/* Scan all the controllers */
	for (i = 0; i < CG_CONTROLLER_MAX; i++) {
		int j = 0;

		if (!controllers[i])
			return 0;
		controller = controllers[i];

		/* If first string is "*" that means all the mounted controllers. */
		if (strcmp(controller, "*") == 0) {
			pthread_rwlock_rdlock(&cg_mount_table_lock);

			for (j = 0; j < CG_CONTROLLER_MAX &&
				cg_mount_table[j].name[0] != '\0'; j++) {
				cgroup_dbg("Adding controller %s\n", cg_mount_table[j].name);
				cptr = cgroup_add_controller(cgroup, cg_mount_table[j].name);
				if (!cptr) {
					cgroup_warn("adding controller '%s' failed\n",
						    cg_mount_table[j].name);
					pthread_rwlock_unlock(&cg_mount_table_lock);
					cgroup_free_controllers(cgroup);
					return ECGROUPNOTALLOWED;
				}
			}
			pthread_rwlock_unlock(&cg_mount_table_lock);
			return ret;
		}

		/* It is individual controller names and not "*" */
		cgroup_dbg("Adding controller %s\n", controller);
		cptr = cgroup_add_controller(cgroup, controller);
		if (!cptr) {
			cgroup_warn("adding controller '%s' failed\n", controller);
			cgroup_free_controllers(cgroup);
			return ECGROUPNOTALLOWED;
		}
	}

	return ret;
}

/**
 * Determines if the rule is a wildcard rule and if so, compares the wildcard
 * rule against the new process.  If the new process matches the wildcard rule,
 * then this function returns true. Otherwise it returns false.
 *
 *	@param rule_procname The procname field of the rule
 *	@param procname The name of the new process
 *	@return True if the procname matches the rule.  False otherwise
 */
STATIC bool cgroup_compare_wildcard_procname(const char * const rule_procname,
					     const char * const procname)
{
	size_t rule_strlen = strlen(rule_procname);

	if (rule_procname[rule_strlen - 1] != '*')
		/* This rule does not end in a wildcard */
		return false;

	/* Compare the two strings up to the asterisk */
	if (strncmp(rule_procname, procname, rule_strlen - 1) != 0)
		/* The strings did not match */
		return false;

	/* All checks passed.  The wildcarded process matched this rule */
	return true;
}

static int cgroup_find_matching_destination(char *cgroup_list[], const char * const rule_dest,
					    int *matching_index)
{
	size_t rule_strlen = strlen(rule_dest);
	int ret = -ENODATA;
	int i;

	for (i = 0; i < MAX_MNT_ELEMENTS; i++) {
		if (cgroup_list[i] == NULL)
			break;

		if (rule_dest[rule_strlen - 1] == '/') {
			/*
			 * Avoid a weird corner case where given a rule dest
			 * like 'folder/', we _don't_ want to match 'folder1'
			 */
			if (strlen(cgroup_list[i]) >= rule_strlen &&
			    cgroup_list[i][rule_strlen - 1] != '/')
				continue;

			/*
			 * Strip off the '/' at the end of the rule, as
			 * the destination from the cgroup_list will not
			 * have a trailing '/'
			 */
			rule_strlen--;
		}

		if (strncmp(rule_dest, cgroup_list[i],
			    rule_strlen) == 0) {
			*matching_index = i;
			ret = 0;
			break;
		}
	}

	return ret;
}

static int cgroup_find_matching_controller(char * const *rule_controllers,
					   const char * const pid_controller, int *matching_index)
{
	int ret = -ENODATA;
	int i;

	for (i = 0; i < MAX_MNT_ELEMENTS; i++) {
		if (rule_controllers[i] == NULL)
			break;

		if (strlen(rule_controllers[i]) != strlen(pid_controller))
			continue;

		if (strncmp(pid_controller, rule_controllers[i], strlen(pid_controller)) == 0) {
			*matching_index = i;
			ret = 0;
			break;
		}
	}

	return ret;
}

/**
 * Evaluates if rule is an ignore rule and the pid/procname match this rule.
 * If rule is an ignore rule and the pid/procname match this rule, then this
 * function returns true.  Otherwise it returns false.
 *
 *	@param rule The rule being evaluated
 *	@param pid PID of the process being compared
 *	@param procname Process name of the process being compared
 *	@return True if the rule is an ignore rule and this pid/procname
 *		match the rule.  False otherwise
 */
STATIC bool cgroup_compare_ignore_rule(const struct cgroup_rule * const rule, pid_t pid,
				       const char * const procname)
{
	char *controller_list[MAX_MNT_ELEMENTS] = { '\0' };
	char *cgroup_list[MAX_MNT_ELEMENTS] = { '\0' };
	int rule_matching_controller_idx;
	int cgroup_list_matching_idx;
	bool found_match = false;
	char *token, *saveptr;
	int ret, i;

	if (!rule->is_ignore)
		/* Immediately return if the 'ignore' option is not set */
		return false;

	ret = cg_get_cgroups_from_proc_cgroups(pid, cgroup_list, controller_list,
					       MAX_MNT_ELEMENTS);
	if (ret < 0)
		goto out;

	ret = cgroup_find_matching_destination(cgroup_list, rule->destination,
					       &cgroup_list_matching_idx);
	if (ret < 0)
		/* No cgroups matched */
		goto out;

	token = strtok_r(controller_list[cgroup_list_matching_idx], ",", &saveptr);
	while (token != NULL) {

		ret = cgroup_find_matching_controller(rule->controllers, token,
						      &rule_matching_controller_idx);
		if (ret == 0)
			/* We found a matching controller */
			break;

		token = strtok_r(NULL, ",", &saveptr);
	}

	if (!rule->procname) {
		/*
		 * The rule procname is empty, thus it's a wildcard and
		 * all processes match.
		 */
		found_match = true;
		goto out;
	}

	if (!strcmp(rule->procname, procname)) {
		found_match = true;
		goto out;
	}

	if (cgroup_compare_wildcard_procname(rule->procname, procname))
		found_match = true;

out:
	for (i = 0; i < MAX_MNT_ELEMENTS; i++) {
		if (controller_list[i])
			free(controller_list[i]);
		if (cgroup_list[i])
			free(cgroup_list[i]);
	}

	return found_match;
}

static struct cgroup_rule *cgroup_find_matching_rule_uid_gid(uid_t uid, gid_t gid,
							     struct cgroup_rule *rule)
{
	/* Temporary user data */
	struct passwd *usr = NULL;

	/* Temporary group data */
	struct group *grp = NULL;

	/* Temporary string pointer */
	char *sp = NULL;

	/* Loop variable */
	int i = 0;

	while (rule) {
		/* Skip "%" which indicates continuation of previous rule. */
		if (rule->username[0] == '%') {
			rule = rule->next;
			continue;
		}
		/* The wildcard rule always matches. */
		if ((rule->uid == CGRULE_WILD) && (rule->gid == CGRULE_WILD))
			return rule;

		/* This is the simple case of the UID matching. */
		if (rule->uid == uid)
			return rule;

		/* This is the simple case of the GID matching. */
		if (rule->gid == gid)
			return rule;

		/* If this is a group rule, the UID might be a member. */
		if (rule->username[0] == '@') {
			/* Get the group data. */
			sp = &(rule->username[1]);
			grp = getgrnam(sp);
			if (!grp) {
				rule = rule->next;
				continue;
			}

			/* Get the data for UID. */
			usr = getpwuid(uid);
			if (!usr) {
				rule = rule->next;
				continue;
			}

			/* If UID is a member of group, we matched. */
			for (i = 0; grp->gr_mem[i]; i++) {
				if (!(strcmp(usr->pw_name, grp->gr_mem[i])))
					return rule;
			}
		}

		/* If we haven't matched, try the next rule. */
		rule = rule->next;
	}

	/* If we get here, no rules matched. */
	return NULL;
}

/**
 * Finds the first rule in the cached list that matches the given UID, GID
 * or PROCESS NAME, and returns a pointer to that rule.
 * This function uses rl_lock.
 *
 * This function may NOT be thread safe.
 *	@param uid The UID to match
 *	@param gid The GID to match
 *	@param procname The PROCESS NAME to match
 *	@return Pointer to the first matching rule, or NULL if no match
 * TODO: Determine thread-safeness and fix if not safe.
 */
static struct cgroup_rule *cgroup_find_matching_rule(uid_t uid, gid_t gid, pid_t pid,
						     const char *procname)
{
	/* Return value */
	struct cgroup_rule *ret = rl.head;
	char *base = NULL;

	pthread_rwlock_wrlock(&rl_lock);
	while (ret) {
		ret = cgroup_find_matching_rule_uid_gid(uid, gid, ret);
		if (!ret)
			break;
		if (cgroup_compare_ignore_rule(ret, pid, procname))
			/*
			 * This pid matched a rule that instructs the
			 * cgrules daemon to ignore this process.
			 */
			break;
		if (ret->is_ignore) {
			/*
			 * The rule currently being examined is an ignore
			 * rule, but it didn't match this pid. Move on to
			 * the next rule
			 */
			ret = ret->next;
			continue;
		}
		if (!procname)
			/* If procname is NULL, return a rule matching UID or GID. */
			break;
		if (!ret->procname)
			/* If no process name in a rule, that means wildcard */
			break;
		if (!strcmp(ret->procname, procname))
			break;

		base = cgroup_basename(procname);
		if (!strcmp(ret->procname, base))
			/* Check a rule of basename. */
			break;
		if (cgroup_compare_wildcard_procname(ret->procname, procname))
			break;
		ret = ret->next;
		free(base);
		base = NULL;
	}
	pthread_rwlock_unlock(&rl_lock);

	if (base)
		free(base);

	return ret;
}

/*
 * Procedure the existence of cgroup "prefix" is in subsystem
 * controller_name return 0 on success
 */
int cgroup_exist_in_subsystem(char *controller_name, char *prefix)
{
	char path[FILENAME_MAX];
	char *ret_path;
	DIR *dir;
	int ret;

	pthread_rwlock_rdlock(&cg_mount_table_lock);
	ret_path = cg_build_path_locked(prefix, path, controller_name);
	pthread_rwlock_unlock(&cg_mount_table_lock);
	if (!ret_path) {
		ret = 1;
		goto end;
	}

	dir = opendir(path);
	if (dir == NULL) {
		/* cgroup in wanted subsystem does not exist */
		ret = 1;
	} else {
		/* cgroup in wanted subsystem exists */
		ret = 0;
		closedir(dir);
	}
end:
	return ret;
}

/*
 * Auxiliary function return a pointer to the string which is copy of
 * input string and end with the slash
 */
char *cgroup_copy_with_slash(char *input)
{
	int len = strlen(input);
	char *output;

	/* If input does not end with '/', allocate one more space for it */
	if ((input[len-1]) != '/')
		len = len+1;

	output = (char *)malloc(sizeof(char)*(len+1));
	if (output == NULL)
		return NULL;

	strcpy(output, input);
	output[len-1] = '/';
	output[len] = '\0';

	return output;
}

/* Add controller to a group if it is not exists create it */
static int add_controller(struct cgroup **pgroup, char *group_name,
			  char controller_name[FILENAME_MAX])
{
	struct cgroup_controller *controller = NULL;
	struct cgroup *group = pgroup[0];
	int ret = 0;

	if  (group == NULL) {
		/* It is the first controllerc the group have to be created */
		group = cgroup_new_cgroup(group_name);
		if (group == NULL) {
			ret = ECGFAIL;
			goto end;
		}
		pgroup[0] = group;
	}

	controller = cgroup_add_controller(group, controller_name);
	if (controller == NULL) {
		cgroup_free(&group);
		ret = ECGFAIL;
	}
end:
	return ret;
}

/*
 * Create control group based given template if the group already don't exist
 * dest is template name with substitute variables tmp is used cgrules rule.
 */
static int cgroup_create_template_group(char *orig_group_name, struct cgroup_rule *tmp, int flags)
{

	struct cgroup *template_group = NULL;
	char *template_name = NULL;	/* Name of the template.              */
	char *group_name = NULL;	/* Name of the group based on         */
					/* template variables are substituted.*/
	char *template_position;	/* Denotes directory in template      */
					/* path which is investigated.        */
	char *group_position;		/* Denotes directory in cgroup path   */
					/* which is investigated.             */

	int ret = 0;
	int exist;
	int i;

	/* Template name and group name have to have '/' sign at the end */
	template_name = cgroup_copy_with_slash(tmp->destination);
	if (template_name == NULL) {
		ret = ECGOTHER;
		last_errno = errno;
		goto end;
	}
	group_name = cgroup_copy_with_slash(orig_group_name);
	if (group_name == NULL) {
		ret = ECGOTHER;
		last_errno = errno;
		free(template_name);
		template_name = NULL;
		goto end;
	}

	/* Set start positions */
	template_position = strchr(template_name, '/');
	group_position = strchr(group_name, '/');

	/*
	 * Go recursively through whole path to template group and create
	 * given directory if it does not exist yet
	 */
	while ((group_position != NULL) && (template_position != NULL)) {
		/* Set new subpath */
		group_position[0] = '\0';
		template_position[0] = '\0';
		template_group = NULL;

		/* Test for which controllers wanted group does not exist */
		i = 0;
		while (tmp->controllers[i] != NULL) {
			exist = cgroup_exist_in_subsystem(tmp->controllers[i], group_name);

			if (exist != 0) {
				/* The cgroup does not exist */
				ret = add_controller(&template_group, group_name,
						     tmp->controllers[i]);
				if  (ret != 0)
					goto while_end;
			}
			i++;
		}

		if (template_group != NULL) {
			/*  New group have to be created */
			if (strcmp(group_name, template_name) == 0) {
				/* The prefix cgroup without template */
				ret = cgroup_create_cgroup(template_group, 0);
			} else {
				/* Use template to create relevant cgroup */
				ret = cgroup_config_create_template_group(template_group,
									  template_name, flags);
			}

			if (ret != 0) {
				cgroup_free(&template_group);
				goto while_end;
			}
			cgroup_dbg("Group %s created - based on template %s\n", group_name,
				   template_name);

			cgroup_free(&template_group);
		}
		template_position[0] = '/';
		group_position[0] = '/';
		template_position = strchr(++template_position, '/');
		group_position = strchr(++group_position, '/');
	}

while_end:
	if ((template_position != NULL) && (template_position[0] == '\0'))
		template_position[0] = '/';
	if ((group_position != NULL) && (group_position[0] == '\0'))
		group_position[0] = '/';

end:
	if (group_name != NULL)
		free(group_name);
	if (template_name != NULL)
		free(template_name);

	return ret;
}

int cgroup_change_cgroup_flags(uid_t uid, gid_t gid, const char *procname, pid_t pid, int flags)
{
	/* Temporary pointer to a rule */
	struct cgroup_rule *tmp = NULL;

	/* Temporary variables for destination substitution */
	char newdest[FILENAME_MAX];
	struct passwd *user_info;
	struct group *group_info;
	int available;
	int written;
	int i, j;

	/* Return codes */
	int ret = 0;

	/* We need to check this before doing anything else! */
	if (!cgroup_initialized) {
		cgroup_warn("libcgroup is not initialized\n");
		ret = ECGROUPNOTINITIALIZED;
		goto finished;
	}

	/*
	 * User had asked to find the matching rule (if one exist) in the
	 * cached rules but the list might be empty due to the inactive
	 * cgrulesengd. Lets emulate its behaviour of caching the rules by
	 * reloading the rules from the configuration file.
	 */
	if ((flags & CGFLAG_USECACHE) && (rl.head == NULL)) {
		cgroup_warn("no cached rules found, trying to reload from %s.\n",
			    CGRULES_CONF_FILE);

		ret = cgroup_reload_cached_rules();
		if (ret != 0)
			goto finished;
	}

	/*
	 * If the user did not ask for cached rules, we must parse the
	 * configuration to find a matching rule (if one exists).
	 * Else, we'll find the first match in the cached list (rl).
	 */
	if (!(flags & CGFLAG_USECACHE)) {
		cgroup_dbg("Not using cached rules for PID %d.\n", pid);
		ret = cgroup_parse_rules(false, uid, gid, procname);

		/* The configuration file has an error!  We must exit now. */
		if (ret != -1 && ret != 0) {
			cgroup_err("failed to parse the configuration rules\n");
			goto finished;
		}

		/* We did not find a matching rule, so we're done. */
		if (ret == 0) {
			cgroup_dbg("No rule found to match PID: %d, UID: %d, GID: %d\n",
				   pid, uid, gid);
			goto finished;
		}

		/* Otherwise, we did match a rule and it's in trl. */
		tmp = trl.head;
	} else {
		/* Find the first matching rule in the cached list. */
		tmp = cgroup_find_matching_rule(uid, gid, pid, procname);
		if (!tmp) {
			cgroup_dbg("No rule found to match PID: %d, UID: %d, GID: %d\n",
				   pid, uid, gid);
			ret = 0;
			goto finished;
		}
	}
	cgroup_dbg("Found matching rule %s for PID: %d, UID: %d, GID: %d\n",
		   tmp->username, pid, uid, gid);

	if (tmp->is_ignore) {
		/*
		 * This rule has instructed us that this pid is not to be
		 * processed and should be ignored
		 */
		cgroup_dbg("Matching rule is an ignore rule\n");
		ret = 0;
		goto finished;
	}

	/* If we are here, then we found a matching rule, so execute it. */
	do {
		cgroup_dbg("Executing rule %s for PID %d... ", tmp->username, pid);

		/* Destination substitutions */
		for (j = i = 0; i < strlen(tmp->destination) &&
			(j < FILENAME_MAX - 2); ++i, ++j) {
			if (tmp->destination[i] == '%') {
				/* How many bytes did we write / error check */
				written = 0;
				/* How many bytes can we write */
				available = FILENAME_MAX - j - 2;
				/* Substitution */
				switch (tmp->destination[++i]) {
				case 'U':
					written = snprintf(newdest+j, available, "%d", uid);
					break;
				case 'u':
					user_info = getpwuid(uid);
					if (user_info) {
						written = snprintf(newdest + j, available, "%s",
								   user_info->pw_name);
					} else {
						written = snprintf(newdest + j, available, "%d",
								   uid);
					}
					break;
				case 'G':
					written = snprintf(newdest + j,	available, "%d", gid);
					break;
				case 'g':
					group_info = getgrgid(gid);
					if (group_info) {
						written = snprintf(newdest + j,	available, "%s",
								   group_info->gr_name);
					} else {
						written = snprintf(newdest + j,	available, "%d",
								   gid);
					}
					break;
				case 'P':
					written = snprintf(newdest + j,	available, "%d", pid);
					break;
				case 'p':
					if (procname) {
						written = snprintf(newdest + j,	available, "%s",
								   procname);
					} else {
						written = snprintf(newdest + j,	available, "%d",
								   pid);
					}
					break;
				}
				written = min(written, available);
				/*
				 * written<1 only when either error occurred
				 * during snprintf or if no substitution was made
				 * at all. In both cases, we want to just copy
				 * input string.
				 */
				if (written < 1) {
					newdest[j] = '%';
					if (available > 1)
						newdest[++j] = tmp->destination[i];
				} else {
					/*
					 * In next iteration, we will write just
					 * after the substitution, but j will get
					 * incremented in the meantime.
					 */
					j += written - 1;
				}
			} else {
				if (tmp->destination[i] == '\\')
					++i;
				newdest[j] = tmp->destination[i];
			}
		}

		newdest[j] = 0;
		if (strcmp(newdest, tmp->destination) != 0) {
			/* Destination tag contains templates */

			cgroup_dbg("control group %s is template\n", newdest);
			ret = cgroup_create_template_group(newdest, tmp, flags);
			if (ret) {
				cgroup_warn("failed to create cgroup based on template %s\n",
					    newdest);
				goto finished;
			}
		}

		/* Apply the rule */
		ret = cgroup_change_cgroup_path(newdest, pid,
						(const char * const *)tmp->controllers);
		if (ret) {
			cgroup_warn("failed to apply the rule. Error was: %d\n", ret);
			goto finished;
		}
		cgroup_dbg("OK!\n");

		/*
		 * Now, check for multi-line rules.  As long as the "next"
		 * rule starts with '%', it's actually part of the rule that
		 * we just executed.
		 */
		tmp = tmp->next;
	} while (tmp && (tmp->username[0] == '%'));

finished:
	return ret;
}

int cgroup_change_cgroup_uid_gid_flags(uid_t uid, gid_t gid, pid_t pid, int flags)
{
	return cgroup_change_cgroup_flags(uid, gid, NULL, pid, flags);
}

/**
 * Provides backwards-compatibility with older versions of the API.
 * This function is deprecated, and cgroup_change_cgroup_uid_gid_flags()
 * should be used instead.  In fact, this function simply calls the newer
 * one with flags set to 0 (none).
 *	@param uid The UID to match
 *	@param gid The GID to match
 *	@param pid The PID of the process to move
 *	@return 0 on success, > 0 on error
 */
int cgroup_change_cgroup_uid_gid(uid_t uid, gid_t gid, pid_t pid)
{
	return cgroup_change_cgroup_uid_gid_flags(uid, gid, pid, 0);
}

/**
 * Changes the cgroup of a program based on the path provided.  In this case,
 * the user must already know into which cgroup the task should be placed and
 * no rules will be parsed.
 *
 *  returns 0 on success.
 */
int cgroup_change_cgroup_path(const char *dest, pid_t pid, const char *const controllers[])
{
	struct dirent *task_dir = NULL;
	char path[FILENAME_MAX];
	struct cgroup cgroup;
	int nr, ret;
	pid_t tid;
	DIR *dir;

	if (!cgroup_initialized) {
		cgroup_warn("libcgroup is not initialized\n");
		return ECGROUPNOTINITIALIZED;
	}
	memset(&cgroup, 0, sizeof(struct cgroup));


	if (is_cgroup_mode_unified() && !controllers) {
		/*
		 * Do not require the user to pass in an array of controller strings on
		 * cgroup v2 systems.  The hierarchy will be the same regardless of
		 * whether controllers are provided or not.
		 */
		strncpy(cgroup.name, dest, FILENAME_MAX);
		cgroup.name[FILENAME_MAX-1] = '\0';
	} else {
		if (!controllers)
			return ECGINVAL;

		ret = cg_prepare_cgroup(&cgroup, pid, dest, controllers);
		if (ret)
			return ret;
	}

	/* Add process to cgroup */
	ret = cgroup_attach_task_pid(&cgroup, pid);
	if (ret) {
		cgroup_warn("cgroup_attach_task_pid failed: %d\n", ret);
		goto finished;
	}

	/* Add all threads to cgroup */
	snprintf(path, FILENAME_MAX, "/proc/%d/task/", pid);
	dir = opendir(path);
	if (!dir) {
		last_errno = errno;
		ret = ECGOTHER;
		goto finished;
	}

	while ((task_dir = readdir(dir)) != NULL) {
		nr = sscanf(task_dir->d_name, "%i", &tid);
		if (nr < 1)
			continue;

		if (tid == pid)
			continue;

		ret = cgroup_attach_task_pid(&cgroup, tid);
		if (ret) {
			cgroup_warn("cgroup_attach_task_pid failed: %d\n", ret);
			break;
		}
	}

	closedir(dir);

finished:
	cgroup_free_controllers(&cgroup);

	return ret;
}

/**
 * Changes the cgroup of all running PIDs based on the rules in the config file.
 * If a rules exists for a PID, then the PID is placed in the correct group.
 *
 * This function may be called after creating new control groups to move
 * running PIDs into the newly created control groups.
 *	@return 0 on success, < 0 on error
 */
int cgroup_change_all_cgroups(void)
{
	struct dirent *pid_dir = NULL;
	char *path = "/proc/";
	DIR *dir;

	dir = opendir(path);
	if (!dir)
		return -ECGOTHER;

	while ((pid_dir = readdir(dir)) != NULL) {
		int err, pid;
		uid_t euid;
		gid_t egid;
		char *procname = NULL;

		err = sscanf(pid_dir->d_name, "%i", &pid);
		if (err < 1)
			continue;

		err = cgroup_get_uid_gid_from_procfs(pid, &euid, &egid);
		if (err)
			continue;

		err = cgroup_get_procname_from_procfs(pid, &procname);
		if (err)
			continue;

		err = cgroup_change_cgroup_flags(euid, egid, procname, pid, CGFLAG_USECACHE);
		if (err)
			cgroup_dbg("cgroup change pid %i failed\n", pid);

		free(procname);
	}

	closedir(dir);
	return 0;
}

/**
 * Print the cached rules table.  This function should be called only after
 * first calling cgroup_parse_config(), but it will work with an empty rule
 * list.
 *	@param fp The file stream to print to
 */
void cgroup_print_rules_config(FILE *fp)
{
	/* Iterator */
	struct cgroup_rule *itr = NULL;

	/* Loop variable */
	int i = 0;

	pthread_rwlock_rdlock(&rl_lock);

	if (!(rl.head)) {
		fprintf(fp, "The rules table is empty.\n\n");
		pthread_rwlock_unlock(&rl_lock);
		return;
	}

	itr = rl.head;
	while (itr) {
		fprintf(fp, "Rule: %s", itr->username);
		if (itr->procname)
			fprintf(fp, ":%s", itr->procname);
		fprintf(fp, "\n");

		if (itr->uid == CGRULE_WILD)
			fprintf(fp, "  UID: any\n");
		else if (itr->uid == CGRULE_INVALID)
			fprintf(fp, "  UID: N/A\n");
		else
			fprintf(fp, "  UID: %d\n", itr->uid);

		if (itr->gid == CGRULE_WILD)
			fprintf(fp, "  GID: any\n");
		else if (itr->gid == CGRULE_INVALID)
			fprintf(fp, "  GID: N/A\n");
		else
			fprintf(fp, "  GID: %d\n", itr->gid);

		fprintf(fp, "  DEST: %s\n", itr->destination);

		fprintf(fp, "  CONTROLLERS:\n");
		for (i = 0; i < MAX_MNT_ELEMENTS; i++) {
			if (itr->controllers[i])
				fprintf(fp, "    %s\n", itr->controllers[i]);
		}
		fprintf(fp, "  OPTIONS:\n");
		if (itr->is_ignore)
			fprintf(fp, "    IS_IGNORE: True\n");
		else
			fprintf(fp, "    IS_IGNORE: False\n");
		fprintf(fp, "\n");
		itr = itr->next;
	}
	pthread_rwlock_unlock(&rl_lock);
}

/**
 * Reloads the rules list, using the given configuration file.
 * This function is probably NOT thread safe (calls cgroup_parse_rules()).
 *	@return 0 on success, > 0 on failure
 */
int cgroup_reload_cached_rules(void)
{
	/* Return codes */
	int ret = 0;

	cgroup_dbg("Reloading cached rules from %s.\n", CGRULES_CONF_FILE);
	ret = cgroup_parse_rules(true, CGRULE_INVALID, CGRULE_INVALID, NULL);
	if (ret) {
		cgroup_warn("error parsing configuration file '%s': %d\n", CGRULES_CONF_FILE, ret);
		ret = ECGRULESPARSEFAIL;
		goto finished;
	}

	#ifdef CGROUP_DEBUG
	cgroup_print_rules_config(stdout);
	#endif
finished:
	return ret;
}

/**
 * Initializes the rules cache.
 *	@return 0 on success, > 0 on error
 */
int cgroup_init_rules_cache(void)
{
	/* Return codes */
	int ret = 0;

	/* Attempt to read the configuration file and cache the rules. */
	ret = cgroup_parse_rules(true, CGRULE_INVALID, CGRULE_INVALID, NULL);
	if (ret) {
		cgroup_dbg("Could not initialize rule cache, error was: %d\n", ret);
	}

	return ret;
}

/**
 * cgroup_get_current_controller_path
 * @pid: pid of the current process for which the path is to be determined
 * @controller: name of the controller for which to determine current path
 * @current_path: a pointer that is filled with the value of the current
 *		path as seen in /proc/<pid>/cgroup
 */
int cgroup_get_current_controller_path(pid_t pid, const char *controller, char **current_path)
{
	FILE *pid_cgroup_fd = NULL;
	enum cg_version_t version;
	enum cg_setup_mode_t mode;
	bool unified = false;
	char *path = NULL;
	int ret;

	if (!cgroup_initialized) {
		cgroup_warn("libcgroup is not initialized\n");
		return ECGROUPNOTINITIALIZED;
	}

	if (!current_path)
		return ECGOTHER;

	mode = cgroup_setup_mode();
	if (mode == CGROUP_MODE_LEGACY && !controller)
		return ECGOTHER;

	/*
	 * both unified/hybrid can have the controller mounted as
	 * cgroup v2 version.
	 */
	if  (!controller) {
		unified = true;
	} else {
		ret = cgroup_get_controller_version(controller, &version);
		if (ret) {
			cgroup_warn("Failed to get version of the controller: %s\n", controller);
			ret = ECGINVAL;
			goto cleanup_path;
		}
		unified = (version == CGROUP_V2);
	}

	ret = asprintf(&path, "/proc/%d/cgroup", pid);
	if (ret <= 0) {
		cgroup_warn("cannot allocate memory (/proc/pid/cgroup) ret %d\n", ret);
		return ret;
	}

	ret = ECGROUPNOTEXIST;
	pid_cgroup_fd = fopen(path, "re");
	if (!pid_cgroup_fd)
		goto cleanup_path;

	/*
	 * Why do we grab the cg_mount_table_lock?, the reason is that the
	 * cgroup of a pid can change via the cgroup_attach_task_pid() call.
	 * To make sure, we return consitent and safe results, we acquire the
	 * lock upfront. We can optimize by acquiring and releasing
	 * the lock in the while loop, but that will be more expensive.
	 */
	pthread_rwlock_rdlock(&cg_mount_table_lock);
	while (!feof(pid_cgroup_fd)) {
		char controllers[FILENAME_MAX];
		char cgroup_path[FILENAME_MAX];
		char *savedptr;
		char *token;
		int num;

		/*
		 * with unified mode, the /proc/pid/cgroup the output is
		 * similar to that of cgroup legacy and hybrid modes:
		 * hierarchy-ID:controller-list:cgroup-path
		 *
		 * the difference is that in cgroup v2:
		 * - hierarchy-ID is always 0 (one hierarchy allowed)
		 * - controller-list is empty
		 */
		if (mode == CGROUP_MODE_UNIFIED || unified) {
			ret = fscanf(pid_cgroup_fd, "%d::%4096s\n", &num, cgroup_path);
			if (ret != 2) {
				/*
				 * we are interested only in unified format
				 * line, skip this line.
				 */
				if (unified) {
					ret = fscanf(pid_cgroup_fd, "%*[^\n]\n");
					if (ret == 0)
						continue;
				}

				cgroup_warn("read failed for pid_cgroup_fd ret %d\n", ret);
				last_errno = errno;
				ret = ECGOTHER;
				goto done;
			}

			/* check if the controller is enabled in cgroup v2 */
			if (controller) {
				ret = cgroupv2_controller_enabled(cgroup_path, controller);
				if (ret)
					goto done;
			}

			*current_path = strdup(cgroup_path);
			if (!*current_path) {
				last_errno = errno;
				ret = ECGOTHER;
				goto done;
			}
			ret = 0;
			goto done;
		}

		/*
		 * 4096 == FILENAME_MAX, keeping the coverity happy with precision
		 * for the cgroup_path.
		 */
		ret = fscanf(pid_cgroup_fd, "%d:%[^:]:%4096s\n", &num, controllers, cgroup_path);
		/*
		 * Magic numbers like "3" seem to be integrating into my daily
		 * life, I need some magic to help make them disappear :)
		 */
		if (ret != 3) {
			cgroup_warn("read failed for pid_cgroup_fd ret %d\n", ret);
			last_errno = errno;
			ret = ECGOTHER;
			goto done;
		}

		token = strtok_r(controllers, ",", &savedptr);
		while (token) {
			if (strncmp(controller, token, strlen(controller) + 1) == 0) {
				*current_path = strdup(cgroup_path);
				if (!*current_path) {
					last_errno = errno;
					ret = ECGOTHER;
					goto done;
				}
				ret = 0;
				goto done;
			}
			token = strtok_r(NULL, ",", &savedptr);
		}
	}

done:
	pthread_rwlock_unlock(&cg_mount_table_lock);
	fclose(pid_cgroup_fd);
cleanup_path:
	free(path);

	return ret;
}

const char *cgroup_strerror(int code)
{
	int idx = code % ECGROUPNOTCOMPILED;

	if (code == ECGOTHER) {
#ifdef STRERROR_R_CHAR_P
		return strerror_r(cgroup_get_last_errno(), errtext, MAXLEN);
#else
		return strerror_r(cgroup_get_last_errno(), errtext, sizeof (errtext)) ?
			"unknown error" : errtext;
#endif
	}
	if (idx >= sizeof(cgroup_strerror_codes)/sizeof(cgroup_strerror_codes[0]))
		return "Invalid error code";

	return cgroup_strerror_codes[idx];
}

/**
 * Return last errno, which caused ECGOTHER error.
 */
int cgroup_get_last_errno(void)
{
	return last_errno;
}

static int cg_walk_node(FTS *fts, FTSENT *ent, const int depth, struct cgroup_file_info *info,
			int dir)
{
	int ret = 0;

	if (!cgroup_initialized)
		return ECGROUPNOTINITIALIZED;

	cgroup_dbg("seeing file %s\n", ent->fts_path);

	info->path = ent->fts_name;
	info->parent = ent->fts_parent->fts_name;
	info->full_path = ent->fts_path;
	info->depth = ent->fts_level;
	info->type = CGROUP_FILE_TYPE_OTHER;

	if (depth && (info->depth > depth))
		return 0;

	switch (ent->fts_info) {
	case FTS_DNR:
	case FTS_ERR:
		errno = ent->fts_errno;
		break;
	case FTS_D:
		if (dir & CGROUP_WALK_TYPE_PRE_DIR)
			info->type = CGROUP_FILE_TYPE_DIR;
		break;
	case FTS_DC:
	case FTS_NSOK:
	case FTS_NS:
	case FTS_DP:
		if (dir & CGROUP_WALK_TYPE_POST_DIR)
			info->type = CGROUP_FILE_TYPE_DIR;
		break;
	case FTS_F:
		info->type = CGROUP_FILE_TYPE_FILE;
		break;
	case FTS_DEFAULT:
		break;
	}
	return ret;
}

int cgroup_walk_tree_next(int depth, void **handle, struct cgroup_file_info *info, int base_level)
{
	struct cgroup_tree_handle *entry;
	FTSENT *ent;
	int ret = 0;

	if (!cgroup_initialized)
		return ECGROUPNOTINITIALIZED;

	if (!handle)
		return ECGINVAL;

	entry = (struct cgroup_tree_handle *) *handle;

	ent = fts_read(entry->fts);
	if (!ent)
		return ECGEOF;
	if (!base_level && depth)
		base_level = ent->fts_level + depth;

	ret = cg_walk_node(entry->fts, ent, base_level, info, entry->flags);

	*handle = entry;
	return ret;
}

int cgroup_walk_tree_end(void **handle)
{
	struct cgroup_tree_handle *entry;

	if (!cgroup_initialized)
		return ECGROUPNOTINITIALIZED;

	if (!handle)
		return ECGINVAL;

	entry = (struct cgroup_tree_handle *) *handle;

	fts_close(entry->fts);
	free(entry);
	*handle = NULL;

	return 0;
}

/* TODO: Need to decide a better place to put this function. */
int cgroup_walk_tree_begin(const char *controller, const char *base_path, int depth, void **handle,
			   struct cgroup_file_info *info, int *base_level)
{
	struct cgroup_tree_handle *entry;
	char full_path[FILENAME_MAX];
	char *cg_path[2];
	FTSENT *ent;
	int ret = 0;

	if (!cgroup_initialized)
		return ECGROUPNOTINITIALIZED;

	if (!handle)
		return ECGINVAL;

	cgroup_dbg("path is %s\n", base_path);

	if (!cg_build_path(base_path, full_path, controller))
		return ECGOTHER;

	entry = calloc(sizeof(struct cgroup_tree_handle), 1);

	if (!entry) {
		last_errno = errno;
		*handle = NULL;
		return ECGOTHER;
	}

	entry->flags |= CGROUP_WALK_TYPE_PRE_DIR;

	*base_level = 0;
	cg_path[0] = full_path;
	cg_path[1] = NULL;

	entry->fts = fts_open(cg_path, FTS_LOGICAL | FTS_NOCHDIR | FTS_NOSTAT, NULL);
	if (entry->fts == NULL) {
		free(entry);
		last_errno = errno;
		*handle = NULL;
		return ECGOTHER;
	}
	ent = fts_read(entry->fts);
	if (!ent) {
		cgroup_warn("fts_read failed\n");
		fts_close(entry->fts);
		free(entry);
		*handle = NULL;
		return ECGINVAL;
	}
	if (!*base_level && depth)
		*base_level = ent->fts_level + depth;

	ret = cg_walk_node(entry->fts, ent, *base_level, info, entry->flags);
	if (ret != 0) {
		fts_close(entry->fts);
		free(entry);
		*handle = NULL;
	} else {
		*handle = entry;
	}
	return ret;
}

int cgroup_walk_tree_set_flags(void **handle, int flags)
{
	struct cgroup_tree_handle *entry;

	if (!cgroup_initialized)
		return ECGROUPNOTINITIALIZED;

	if (!handle)
		return ECGINVAL;

	if ((flags & CGROUP_WALK_TYPE_PRE_DIR) &&
	    (flags & CGROUP_WALK_TYPE_POST_DIR))
		return ECGINVAL;

	entry = (struct cgroup_tree_handle *) *handle;
	entry->flags = flags;

	*handle = entry;
	return 0;
}

/*
 * This parses a stat line which is in the form of (name value) pair
 * separated by a space.
 */
static int cg_read_stat(FILE *fp, struct cgroup_stat *cgroup_stat)
{
	char *saveptr = NULL;
	ssize_t read_bytes;
	char *line = NULL;
	size_t len = 0;
	char *token;
	int ret = 0;

	read_bytes = getline(&line, &len, fp);
	if (read_bytes == -1) {
		ret = ECGEOF;
		goto out_free;
	}

	token = strtok_r(line, " ", &saveptr);
	if (!token) {
		ret = ECGINVAL;
		goto out_free;
	}
	strncpy(cgroup_stat->name, token, FILENAME_MAX - 1);

	token = strtok_r(NULL, " ", &saveptr);
	if (!token) {
		ret = ECGINVAL;
		goto out_free;
	}
	strncpy(cgroup_stat->value, token, CG_VALUE_MAX - 1);

out_free:
	free(line);

	return ret;
}

int cgroup_read_value_end(void **handle)
{
	FILE *fp;

	if (!cgroup_initialized)
		return ECGROUPNOTINITIALIZED;

	if (!handle)
		return ECGINVAL;

	fp = (FILE *)*handle;
	fclose(fp);
	*handle = NULL;

	return 0;
}

int cgroup_read_value_next(void **handle, char *buffer, int max)
{
	char *ret_c;
	int ret = 0;
	FILE *fp;

	if (!cgroup_initialized)
		return ECGROUPNOTINITIALIZED;

	if (!buffer || !handle)
		return ECGINVAL;

	fp = (FILE *)*handle;
	ret_c = fgets(buffer, max, fp);
	if (ret_c == NULL)
		ret = ECGEOF;

	return ret;
}

int cgroup_read_value_begin(const char * const controller, const char *path,
			    const char * const name, void **handle, char *buffer, int max)
{
	char stat_file[FILENAME_MAX + sizeof(name)];
	char stat_path[FILENAME_MAX];
	char *ret_c = NULL;
	int ret = 0;
	FILE *fp;

	if (!cgroup_initialized)
		return ECGROUPNOTINITIALIZED;

	if (!buffer || !handle)
		return ECGINVAL;

	if (!cg_build_path(path, stat_path, controller))
		return ECGOTHER;

	snprintf(stat_file, sizeof(stat_file), "%s/%s", stat_path,
		 name);
	fp = fopen(stat_file, "re");
	if (!fp) {
		cgroup_warn("fopen failed\n");
		last_errno = errno;
		*handle = NULL;
		return ECGOTHER;
	}

	ret_c = fgets(buffer, max, fp);
	if (ret_c == NULL)
		ret = ECGEOF;

	*handle = fp;

	return ret;
}

int cgroup_read_stats_end(void **handle)
{
	FILE *fp;

	if (!cgroup_initialized)
		return ECGROUPNOTINITIALIZED;

	if (!handle)
		return ECGINVAL;

	fp = (FILE *)*handle;
	if (fp == NULL)
		return ECGINVAL;

	fclose(fp);

	return 0;
}

int cgroup_read_stats_next(void **handle, struct cgroup_stat *cgroup_stat)
{
	FILE *fp;
	int ret = 0;

	if (!cgroup_initialized)
		return ECGROUPNOTINITIALIZED;

	if (!handle || !cgroup_stat)
		return ECGINVAL;

	fp = (FILE *)*handle;
	ret = cg_read_stat(fp, cgroup_stat);
	*handle = fp;

	return ret;
}

/* TODO: Need to decide a better place to put this function. */
int cgroup_read_stats_begin(const char *controller, const char *path, void **handle,
			    struct cgroup_stat *cgroup_stat)
{
	char stat_file[FILENAME_MAX + sizeof(".stat")];
	char stat_path[FILENAME_MAX];
	int ret = 0;
	FILE *fp;

	if (!cgroup_initialized)
		return ECGROUPNOTINITIALIZED;

	if (!cgroup_stat || !handle)
		return ECGINVAL;

	if (!cg_build_path(path, stat_path, controller))
		return ECGOTHER;

	snprintf(stat_file, sizeof(stat_file), "%s/%s.stat", stat_path, controller);

	fp = fopen(stat_file, "re");
	if (!fp) {
		cgroup_warn("fopen failed\n");
		return ECGINVAL;
	}

	ret = cg_read_stat(fp, cgroup_stat);
	*handle = fp;

	return ret;
}

int cgroup_get_task_end(void **handle)
{
	if (!cgroup_initialized)
		return ECGROUPNOTINITIALIZED;

	if (!*handle)
		return ECGINVAL;

	fclose((FILE *) *handle);
	*handle = NULL;

	return 0;
}

int cgroup_get_task_next(void **handle, pid_t *pid)
{
	int ret;

	if (!cgroup_initialized)
		return ECGROUPNOTINITIALIZED;

	if (!handle)
		return ECGINVAL;

	ret = fscanf((FILE *) *handle, "%u", pid);

	if (ret != 1) {
		if (ret == EOF)
			return ECGEOF;
		last_errno = errno;
		return ECGOTHER;
	}

	return 0;
}

int cgroup_get_task_begin(const char *cgroup, const char *controller, void **handle, pid_t *pid)
{
	char path[FILENAME_MAX];
	char *fullpath = NULL;
	int ret = 0;

	if (!cgroup_initialized)
		return ECGROUPNOTINITIALIZED;

	if (!cg_build_path(cgroup, path, controller))
		return ECGOTHER;

	ret = asprintf(&fullpath, "%s/tasks", path);

	if (ret < 0) {
		last_errno = errno;
		return ECGOTHER;
	}

	*handle = (void *) fopen(fullpath, "re");
	free(fullpath);

	if (!*handle) {
		last_errno = errno;
		return ECGOTHER;
	}
	ret = cgroup_get_task_next(handle, pid);

	return ret;
}

int cgroup_get_controller_end(void **handle)
{
	int *pos = (int *) *handle;

	if (!cgroup_initialized)
		return ECGROUPNOTINITIALIZED;

	if (!pos)
		return ECGINVAL;

	free(pos);
	*handle = NULL;

	return 0;
}

int cgroup_get_controller_next(void **handle, struct cgroup_mount_point *info)
{
	int *pos = (int *) *handle;
	int ret = 0;

	if (!cgroup_initialized)
		return ECGROUPNOTINITIALIZED;

	if (!pos)
		return ECGINVAL;

	if (!info)
		return ECGINVAL;

	pthread_rwlock_rdlock(&cg_mount_table_lock);

	if (cg_mount_table[*pos].name[0] == '\0') {
		ret = ECGEOF;
		goto out_unlock;
	}

	if (strncmp(cg_mount_table[*pos].name, CGROUP_FILE_PREFIX, CONTROL_NAMELEN_MAX) == 0)
		/*
		 * For now, hide the "cgroup" pseudo-controller from the user.  This may be
		 * worth revisiting in the future.
		 */
		(*pos)++;

	if (cg_mount_table[*pos].name[0] == '\0') {
		ret = ECGEOF;
		goto out_unlock;
	}

	strncpy(info->name, cg_mount_table[*pos].name, FILENAME_MAX - 1);
	info->name[FILENAME_MAX - 1] = '\0';

	strncpy(info->path, cg_mount_table[*pos].mount.path, FILENAME_MAX - 1);
	info->path[FILENAME_MAX - 1] = '\0';

	(*pos)++;
	*handle = pos;

out_unlock:
	pthread_rwlock_unlock(&cg_mount_table_lock);

	return ret;
}

int cgroup_get_controller_begin(void **handle, struct cgroup_mount_point *info)
{
	int *pos;

	if (!cgroup_initialized)
		return ECGROUPNOTINITIALIZED;

	if (!info)
		return ECGINVAL;

	pos = malloc(sizeof(int));

	if (!pos) {
		last_errno = errno;
		return ECGOTHER;
	}

	*pos = 0;

	*handle = pos;

	return cgroup_get_controller_next(handle, info);
}

/**
 * Get process data (euid and egid) from /proc/<pid>/status file.
 * @param pid: The process id
 * @param euid: The uid of param pid
 * @param egid: The gid of param pid
 * @return 0 on success, > 0 on error.
 */
int cgroup_get_uid_gid_from_procfs(pid_t pid, uid_t *euid, gid_t *egid)
{
	char path[FILENAME_MAX];
	uid_t ruid, suid, fsuid;
	gid_t rgid, sgid, fsgid;
	bool found_euid = false;
	bool found_egid = false;
	char buf[4092];
	FILE *f;

	sprintf(path, "/proc/%d/status", pid);
	f = fopen(path, "re");
	if (!f)
		return ECGROUPNOTEXIST;

	while (fgets(buf, sizeof(buf), f)) {
		if (!strncmp(buf, "Uid:", 4)) {
			if (sscanf((buf + strlen("Uid:") + 1), "%d%d%d%d",
				    &ruid, euid, &suid, &fsuid) != 4)
				break;
			cgroup_dbg("Scanned proc values are %d %d %d %d\n",
				   ruid, *euid, suid, fsuid);
			found_euid = true;
		} else if (!strncmp(buf, "Gid:", 4)) {
			if (sscanf((buf + strlen("Gid:") + 1), "%d%d%d%d",
				   &rgid, egid, &sgid, &fsgid) != 4)
				break;
			cgroup_dbg("Scanned proc values are %d %d %d %d\n",
				   rgid, *egid, sgid, fsgid);
			found_egid = true;
		}
		if (found_euid && found_egid)
			break;
	}
	fclose(f);
	if (!found_euid || !found_egid) {
		/*
		 * This method doesn't match the file format of
		 * /proc/<pid>/status. The format has been changed and we
		 * should catch up the change.
		 */
		cgroup_warn("invalid file format of /proc/%d/status\n", pid);
		return ECGFAIL;
	}
	return 0;
}

/**
 * Given a pid, this function will return the controllers and cgroups that
 * the pid is a member of. The caller is expected to allocate the
 * controller_list[] and cgroup_list[] arrays as well as null each entry in
 * the arrays.  This function will allocate the necessary memory for each
 * string within the arrays.
 *
 *	@param pid The process id
 *	@param cgroup_list[] An array of char pointers to hold the cgroups
 *	@param controller_list[] An array of char pointers to hold the list
 *	       of controllers
 *	@param list_len The size of the arrays
 */
STATIC int cg_get_cgroups_from_proc_cgroups(pid_t pid, char *cgroup_list[],
					    char *controller_list[], int list_len)
{
	char path[FILENAME_MAX];
	char *stok_buff = NULL;
	size_t buff_len;
	char buf[4092];
	int ret = 0;
	int idx = 0;
	FILE *f;

#ifdef UNIT_TEST
	sprintf(path, "%s", TEST_PROC_PID_CGROUP_FILE);
#else
	sprintf(path, "/proc/%d/cgroup", pid);
#endif
	f = fopen(path, "re");
	if (!f)
		return ECGROUPNOTEXIST;

	while (fgets(buf, sizeof(buf), f)) {
		/*
		 * Each line in /proc/{pid}/cgroup is like the following:
		 *
		 * {cg#}:{controller}:{cgname}
		 *
		 * e.g.
		 * 7:devices:/user.slice
		 */

		/* Read in the cgroup number.  we don't care about it */
		stok_buff = strtok(buf, ":");
		/* Read in the controller name */
		stok_buff = strtok(NULL, ":");

		/*
		 * After this point, we have allocated memory.  if we return
		 * an error code after this, it's up to us to free the memory
		 * we allocated
		 */
		controller_list[idx] = strndup(stok_buff, strlen(stok_buff) + 1);

		/* Read in the cgroup name */
		stok_buff = strtok(NULL, ":");

		if (stok_buff == NULL) {
			/*
			 * An empty controller is reported on some kernels.
			 * It may look like this:
			 * 0::/user.slice/user-1000.slice/session-1.scope
			 *
			 * Ignore this controller and move on.  Note that we
			 * need to free the controller list entry we made.
			 */
			free(controller_list[idx]);
			controller_list[idx] = NULL;
			continue;
		}

		buff_len = strlen(stok_buff);
		if (stok_buff[buff_len - 1] == '\n')
			buff_len--; /* Don't copy the trailing newline char */

		/* Read in the cgroup name */
		if (buff_len > 1) {
			/* Strip off the leading '/' for every cgroup but the root cgroup */
			cgroup_list[idx] = malloc(buff_len);
			snprintf(cgroup_list[idx], buff_len, "%s", &stok_buff[1]);
		} else {
			/* Retain the leading '/' since we're in the root cgroup */
			cgroup_list[idx] = strndup(stok_buff, buff_len);
		}

		idx++;
		if (idx >= list_len) {
			cgroup_warn("Maximum mount elements reached. Consider increasing ");
			cgroup_warn("MAX_MNT_ELEMENTS\n");
			break;
		}
	}
	fclose(f);
	return ret;
}

/**
 * Get process name from /proc/<pid>/status file.
 * @param pid: The process id
 * @param pname_status : The process name
 * @return 0 on success, > 0 on error.
 */
static int cg_get_procname_from_proc_status(pid_t pid, char **procname_status)
{
	char path[FILENAME_MAX];
	int ret = ECGFAIL;
	char buf[4092];
	FILE *f;
	int len;

	sprintf(path, "/proc/%d/status", pid);
	f = fopen(path, "re");
	if (!f)
		return ECGROUPNOTEXIST;

	while (fgets(buf, sizeof(buf), f)) {
		if (!strncmp(buf, "Name:", 5)) {
			len = strlen(buf);
			if (buf[len - 1] == '\n')
				buf[len - 1] = '\0';
			*procname_status = strdup(buf + strlen("Name:") + 1);
			if (*procname_status == NULL) {
				last_errno = errno;
				ret = ECGOTHER;
				break;
			}
			ret = 0;
			break;
		}
	}
	fclose(f);
	return ret;
}

/**
 * Get process name from /proc/<pid>/cmdline file.
 * This function is mainly for getting a script name (shell, perl, etc).
 * A script name is written into the second or later argument of
 * /proc/<pid>/cmdline. This function gets each argument and
 * compares it to a process name taken from /proc/<pid>/status.
 * @param pid: The process id
 * @param pname_status : The process name taken from /proc/<pid>/status
 * @param pname_cmdline: The process name taken from /proc/<pid>/cmdline
 * @return 0 on success, > 0 on error.
 */
static int cg_get_procname_from_proc_cmdline(pid_t pid, const char *pname_status,
					     char **pname_cmdline)
{
	char pid_cwd_path[FILENAME_MAX];
	char pid_cmd_path[FILENAME_MAX];
	char buf_pname[FILENAME_MAX];
	char buf_cwd[FILENAME_MAX];
	int ret = ECGFAIL;
	int len = 0;
	int c = 0;
	FILE *f;

	memset(buf_cwd, '\0', sizeof(buf_cwd));
	sprintf(pid_cwd_path, "/proc/%d/cwd", pid);

	if (readlink(pid_cwd_path, buf_cwd, sizeof(buf_cwd)) < 0)
		return ECGROUPNOTEXIST;

	/* readlink doesn't append a null */
	buf_cwd[FILENAME_MAX - 1] = '\0';

	sprintf(pid_cmd_path, "/proc/%d/cmdline", pid);
	f = fopen(pid_cmd_path, "re");
	if (!f)
		return ECGROUPNOTEXIST;

	while (c != EOF) {
		c = fgetc(f);
		if ((c != EOF) && (c != '\0') && (len < FILENAME_MAX - 1)) {
			buf_pname[len] = c;
			len++;
			continue;
		}
		buf_pname[len] = '\0';

		if (len == FILENAME_MAX - 1)
			while ((c != EOF) && (c != '\0'))
				c = fgetc(f);

		/*
		 * The taken process name from /proc/<pid>/status is
		 * shortened to 15 characters if it is over. So the name
		 * should be compared by its length.
		 */
		if (strncmp(pname_status, basename(buf_pname), TASK_COMM_LEN - 1)) {
			len = 0;
			continue;
		}

		if (buf_pname[0] == '/') {
			*pname_cmdline = strdup(buf_pname);
			if (*pname_cmdline == NULL) {
				last_errno = errno;
				ret = ECGOTHER;
				break;
			}
			ret = 0;
			break;
		}

		strcat(buf_cwd, "/");
		strcat(buf_cwd, buf_pname);
		if (!realpath(buf_cwd, pid_cmd_path)) {
			last_errno = errno;
			ret = ECGOTHER;
			break;
		}

		*pname_cmdline = strdup(pid_cmd_path);
		if (*pname_cmdline == NULL) {
			last_errno = errno;
			ret = ECGOTHER;
			break;
		}
		ret = 0;
		break;
	}
	fclose(f);
	return ret;
}

/**
 * Get a process name from /proc file system.
 * This function allocates memory for a process name, writes a process
 * name onto it. So a caller should free the memory when unusing it.
 * @param pid: The process id
 * @param procname: The process name
 * @return 0 on success, > 0 on error.
 */
int cgroup_get_procname_from_procfs(pid_t pid, char **procname)
{
	char path[FILENAME_MAX];
	char buf[FILENAME_MAX];
	char *pname_cmdline;
	char *pname_status;
	int ret;

	ret = cg_get_procname_from_proc_status(pid, &pname_status);
	if (ret)
		return ret;

	/* Get the full patch of process name from /proc/<pid>/exe. */
	memset(buf, '\0', sizeof(buf));
	snprintf(path, FILENAME_MAX, "/proc/%d/exe", pid);
	if (readlink(path, buf, sizeof(buf)) < 0) {
		/*
		 * readlink() fails if a kernel thread, and a process name
		 * is taken from /proc/<pid>/status.
		 */
		*procname = pname_status;
		return 0;
	}
	/* readlink doesn't append a null */
	buf[FILENAME_MAX - 1] = '\0';

	if (!strncmp(pname_status, basename(buf), TASK_COMM_LEN - 1)) {
		/*
		 * The taken process name from /proc/<pid>/status is
		 * shortened to 15 characters if it is over. So the name
		 * should be compared by its length.
		 */
		free(pname_status);
		*procname = strdup(buf);
		if (*procname == NULL) {
			last_errno = errno;
			return ECGOTHER;
		}
		return 0;
	}

	/*
	 * The above strncmp() is not 0 if a shell script, because
	 * /proc/<pid>/exe links a shell command (/bin/bash etc.) and the
	 * pname_status represents a shell script name. Then the full path
	 * of a shell script is taken from /proc/<pid>/cmdline.
	 */
	ret = cg_get_procname_from_proc_cmdline(pid, pname_status, &pname_cmdline);
	if (!ret) {
		*procname = pname_cmdline;
		free(pname_status);
		return 0;
	}

	/*
	 * The above strncmp() is not 0 also if executing a symbolic link,
	 * /proc/pid/exe points to real executable name then. Return it as
	 * the last resort.
	 */
	free(pname_status);
	*procname = strdup(buf);
	if (*procname == NULL) {
		last_errno = errno;
		return ECGOTHER;
	}

	return 0;
}

int cgroup_register_unchanged_process(pid_t pid, int flags)
{
	char buff[sizeof(CGRULE_SUCCESS_STORE_PID)];
	struct sockaddr_un addr;
	size_t ret_len;
	int ret = 1;
	int sk;

	sk = socket(PF_UNIX, SOCK_STREAM, 0);
	if (sk < 0)
		return 1;

	memset(&addr, 0, sizeof(addr));
	addr.sun_family = AF_UNIX;
	strcpy(addr.sun_path, CGRULE_CGRED_SOCKET_PATH);

	if (connect(sk, (struct sockaddr *)&addr,
	    sizeof(addr.sun_family) + strlen(CGRULE_CGRED_SOCKET_PATH)) < 0) {
		/* If the daemon does not work, this function returns 0 as success. */
		ret = 0;
		goto close;
	}
	if (write(sk, &pid, sizeof(pid)) < 0)
		goto close;

	if (write(sk, &flags, sizeof(flags)) < 0)
		goto close;

	ret_len = read(sk, buff, sizeof(buff));
	if (ret_len != sizeof(buff))
		goto close;

	if (strncmp(buff, CGRULE_SUCCESS_STORE_PID, sizeof(buff)))
		goto close;

	ret = 0;
close:
	close(sk);

	return ret;
}

int cgroup_get_subsys_mount_point(const char *controller, char **mount_point)
{
	int ret = ECGROUPNOTEXIST;
	int i;

	if (!cgroup_initialized)
		return ECGROUPNOTINITIALIZED;

	if (!controller)
		return ECGINVAL;

	pthread_rwlock_rdlock(&cg_mount_table_lock);
	for (i = 0; cg_mount_table[i].name[0] != '\0'; i++) {
		if (strncmp(cg_mount_table[i].name, controller, FILENAME_MAX))
			continue;

		*mount_point = strdup(cg_mount_table[i].mount.path);

		if (!*mount_point) {
			last_errno = errno;
			ret = ECGOTHER;
			goto out_exit;
		}

		ret = 0;
		break;
	}
out_exit:
	pthread_rwlock_unlock(&cg_mount_table_lock);

	return ret;
}

int cgroup_get_all_controller_end(void **handle)
{
	FILE *proc_cgroup = (FILE *) *handle;

	if (!proc_cgroup)
		return ECGINVAL;

	fclose(proc_cgroup);
	*handle = NULL;

	return 0;
}

int cgroup_get_all_controller_next(void **handle, struct controller_data *info)
{
	FILE *proc_cgroup = (FILE *) *handle;
	int hierarchy, num_cgroups, enabled;
	char subsys_name[FILENAME_MAX];
	int err = 0;

	if (!proc_cgroup)
		return ECGINVAL;

	if (!info)
		return ECGINVAL;

	/*
	 * check Linux Kernel sources/kernel/cgroup/cgroup.c cgroup_init_early(),
	 * MAX_CGROUP_TYPE_NAMELEN check for details on why 32 is used.
	 */
	err = fscanf(proc_cgroup, "%32s %d %d %d\n", subsys_name, &hierarchy, &num_cgroups,
		     &enabled);

	if (err != 4)
		return ECGEOF;

	strncpy(info->name, subsys_name, FILENAME_MAX);
	info->name[FILENAME_MAX-1] = '\0';
	info->hierarchy = hierarchy;
	info->num_cgroups = num_cgroups;
	info->enabled = enabled;

	return 0;
}

int cgroup_get_all_controller_begin(void **handle, struct controller_data *info)
{
	FILE *proc_cgroup = NULL;
	char buf[FILENAME_MAX];
	int ret;

	if (!info)
		return ECGINVAL;

	proc_cgroup = fopen("/proc/cgroups", "re");
	if (!proc_cgroup) {
		last_errno = errno;
		return ECGOTHER;
	}

	if (!fgets(buf, FILENAME_MAX, proc_cgroup)) {
		last_errno = errno;
		fclose(proc_cgroup);
		*handle = NULL;
		return ECGOTHER;
	}
	*handle = proc_cgroup;

	ret = cgroup_get_all_controller_next(handle, info);
	if (ret != 0) {
		fclose(proc_cgroup);
		*handle = NULL;
	}

	return ret;
}

static int pid_compare(const void *a, const void *b)
{
	const pid_t *pid1, *pid2;

	pid1 = (pid_t *) a;
	pid2 = (pid_t *) b;

	return (*pid1 - *pid2);
}

/* pids needs to be completely uninitialized so that we can set it up
 *
 * Caller must free up pids.
 */
int cgroup_get_procs(char *name, char *controller, pid_t **pids, int *size)
{
	char cgroup_path[FILENAME_MAX];
	int tot_procs = 16;
	pid_t *tmp_list;
	FILE *procs;
	int n = 0;
	int err;

	cg_build_path(name, cgroup_path, controller);
	strncat(cgroup_path, "/cgroup.procs", FILENAME_MAX-strlen(cgroup_path));

	procs = fopen(cgroup_path, "r");
	if (!procs) {
		last_errno = errno;
		*pids = NULL;
		*size = 0;
		if (errno == ENOENT)
			return ECGROUPUNSUPP;
		else
			return ECGOTHER;
	}

	/* Keep doubling the memory allocated if needed */
	tmp_list = malloc(sizeof(pid_t) * tot_procs);
	if (!tmp_list) {
		last_errno = errno;
		fclose(procs);
		return ECGOTHER;
	}

	while (!feof(procs)) {
		while (!feof(procs) && n < tot_procs) {
			pid_t pid;

			err = fscanf(procs, "%u", &pid);
			if (err == EOF)
				break;
			tmp_list[n] = pid;
			n++;
		}
		if (!feof(procs)) {
			pid_t *orig_list = tmp_list;

			tot_procs *= 2;
			tmp_list = realloc(tmp_list, sizeof(pid_t) * tot_procs);
			if (!tmp_list) {
				last_errno = errno;
				fclose(procs);
				free(orig_list);
				*pids = NULL;
				*size = 0;
				return ECGOTHER;
			}
		}
	}
	fclose(procs);

	*size = n;
	qsort(tmp_list, n, sizeof(pid_t), &pid_compare);
	*pids = tmp_list;

	return 0;
}

int cgroup_dictionary_create(struct cgroup_dictionary **dict,
			     int flags)
{
	if (!dict)
		return ECGINVAL;

	*dict = (struct cgroup_dictionary *) calloc(1, sizeof(struct cgroup_dictionary));
	if (!*dict) {
		last_errno = errno;
		return ECGOTHER;
	}
	(*dict)->flags = flags;

	return 0;
}

int cgroup_dictionary_add(struct cgroup_dictionary *dict, const char *name, const char *value)
{
	struct cgroup_dictionary_item *it;

	if (!dict)
		return ECGINVAL;

	it = (struct cgroup_dictionary_item *) malloc(sizeof(struct cgroup_dictionary_item));
	if (!it) {
		last_errno = errno;
		return ECGOTHER;
	}

	it->next = NULL;
	it->name = name;
	it->value = value;

	if (dict->tail) {
		dict->tail->next = it;
		dict->tail = it;
	} else {
		/* It is the first item */
		dict->tail = it;
		dict->head = it;
	}

	return 0;
}

int cgroup_dictionary_free(struct cgroup_dictionary *dict)
{
	struct cgroup_dictionary_item *it;

	if (!dict)
		return ECGINVAL;

	it = dict->head;
	while (it) {
		struct cgroup_dictionary_item *del = it;

		it = it->next;
		if (!(dict->flags & CG_DICT_DONT_FREE_ITEMS)) {
			free((void *)del->value);
			free((void *)del->name);
		}
		free(del);
	}
	free(dict);

	return 0;
}

int cgroup_dictionary_iterator_begin(struct cgroup_dictionary *dict, void **handle,
				     const char **name, const char **value)
{
	struct cgroup_dictionary_iterator *iter;

	*handle = NULL;

	if (!dict)
		return ECGINVAL;

	iter = (struct cgroup_dictionary_iterator *)
		malloc(sizeof(struct cgroup_dictionary_iterator));
	if (!iter) {
		last_errno = errno;
		return ECGOTHER;
	}

	iter->item = dict->head;
	*handle = iter;

	return cgroup_dictionary_iterator_next(handle, name, value);
}

int cgroup_dictionary_iterator_next(void **handle, const char **name, const char **value)
{
	struct cgroup_dictionary_iterator *iter;

	if (!handle)
		return ECGINVAL;

	iter = *handle;

	if (!iter)
		return ECGINVAL;

	if (!iter->item)
		return ECGEOF;

	*name = iter->item->name;
	*value = iter->item->value;
	iter->item = iter->item->next;

	return 0;
}

void cgroup_dictionary_iterator_end(void **handle)
{
	if (!handle)
		return;

	free(*handle);
	*handle = NULL;
}

int cgroup_get_subsys_mount_point_begin(const char *controller, void **handle, char *path)
{
	int i;

	if (!cgroup_initialized)
		return ECGROUPNOTINITIALIZED;
	if (!handle || !path || !controller)
		return ECGINVAL;

	for (i = 0; cg_mount_table[i].name[0] != '\0'; i++)
		if (strcmp(controller, cg_mount_table[i].name) == 0)
			break;

	if (cg_mount_table[i].name[0] == '\0') {
		/* The controller is not mounted at all */
		*handle = NULL;
		*path = '\0';
		return ECGEOF;
	}

	/*
	 * 'handle' is pointer to struct cg_mount_point, which should be
	 * returned next.
	 */
	*handle = cg_mount_table[i].mount.next;
	strcpy(path, cg_mount_table[i].mount.path);

	return 0;
}

int cgroup_get_subsys_mount_point_next(void **handle, char *path)
{
	struct cg_mount_point *it;

	if (!cgroup_initialized)
		return ECGROUPNOTINITIALIZED;
	if (!handle || !path)
		return ECGINVAL;

	it = *handle;
	if (!it) {
		*handle = NULL;
		*path = '\0';
		return ECGEOF;
	}

	*handle = it->next;
	strcpy(path, it->path);

	return 0;
}

int cgroup_get_subsys_mount_point_end(void **handle)
{
	if (!cgroup_initialized)
		return ECGROUPNOTINITIALIZED;

	if (!handle)
		return ECGINVAL;

	*handle = NULL;

	return 0;
}

int cgroup_get_controller_version(const char * const controller, enum cg_version_t * const version)
{
	int i;

	if (!version)
		return ECGINVAL;

	if (!controller && strlen(cg_cgroup_v2_mount_path) > 0) {
		*version = CGROUP_V2;
		return 0;
	}

	if (!controller)
		return ECGINVAL;

	*version = CGROUP_UNK;

	for (i = 0; cg_mount_table[i].name[0] != '\0'; i++) {
		if (strncmp(cg_mount_table[i].name, controller,
			    sizeof(cg_mount_table[i].name)) == 0) {
			*version = cg_mount_table[i].version;
			return 0;
		}
	}

	return ECGROUPNOTEXIST;
}

static int search_and_append_mnt_path(struct cg_mount_point **mount_point, char *path)
{
	struct cg_mount_point *mnt_point, *mnt_tmp, *mnt_prev;

	mnt_tmp = *mount_point;
	while(mnt_tmp) {
		if (strcmp(mnt_tmp->path, path) == 0)
			return ECGVALUEEXISTS;

		mnt_prev = mnt_tmp;
		mnt_tmp = mnt_tmp->next;
	}

	mnt_point = malloc(sizeof(struct cg_mount_point));
	if (mnt_point == NULL) {
		last_errno = errno;
		return ECGOTHER;
	}

	strncpy(mnt_point->path, path, FILENAME_MAX - 1);
	mnt_point->path[FILENAME_MAX - 1] = '\0';

	mnt_point->next = NULL;

	if (*mount_point == NULL)
		*mount_point = mnt_point;
	else
		mnt_prev->next = mnt_point;

	return 0;
}

/**
 * List the mount paths, that matches the specified version
 *
 *	@param cgrp_version The cgroup type/version
 *	@param mount_paths Holds the list of mount paths
 *	@return 0 success and list of mounts paths in mount_paths
 *		ECGOTHER on failure and mount_paths is NULL.
 */
int cgroup_list_mount_points(const enum cg_version_t cgrp_version, char ***mount_paths)
{
	struct cg_mount_point *mount_point, *mnt_tmp = NULL;
	char **mnt_paths = NULL;
	int i, idx = 0;
	int ret;

	if (!cgroup_initialized)
		return ECGROUPNOTINITIALIZED;

	if (cgrp_version != CGROUP_V1 && cgrp_version != CGROUP_V2)
		return ECGINVAL;

	pthread_rwlock_rdlock(&cg_mount_table_lock);

	for (i = 0; cg_mount_table[i].name[0] != '\0'; i++) {
		if (cg_mount_table[i].version != cgrp_version)
			continue;

		mount_point = &cg_mount_table[i].mount;
		while (mount_point) {
			ret = search_and_append_mnt_path(&mnt_tmp, mount_point->path);
			if (ret != 0 && ret != ECGVALUEEXISTS)
				goto err;

			/* Avoid adding duplicate mount points */
			if (ret != ECGVALUEEXISTS)
				idx++;

			mount_point = mount_point->next;
		}
	}

	/*
	 * Cgroup v2 can be mounted without any controller and these mount
	 * paths are not part of the cg_mount_table.  Check and append
	 * them to mnt_paths.
	 */
	if (cgrp_version == CGROUP_V2 && cg_cgroup_v2_empty_mount_paths ) {
		mount_point = cg_cgroup_v2_empty_mount_paths;
		while(mount_point) {
			ret = search_and_append_mnt_path(&mnt_tmp, mount_point->path);
			if (ret)
				goto err;

			idx++;
			mount_point = mount_point->next;
		}
	}

	mnt_paths = malloc(sizeof(char*) * (idx + 1));
	if (mnt_paths == NULL) {
		last_errno = errno;
		ret = ECGOTHER;
		goto err;
	}

	for (i = 0, mount_point = mnt_tmp;
	     mount_point;
	     mount_point = mount_point->next, i++) {

		mnt_paths[i] = strdup(mount_point->path);
		if (mnt_paths[i] == NULL) {
			last_errno = errno;
			ret = ECGOTHER;
			goto err;
		}
	}
	mnt_paths[i] = '\0';

	ret = 0;
	*mount_paths = mnt_paths;

err:
	pthread_rwlock_unlock(&cg_mount_table_lock);

	while(mnt_tmp) {
		mount_point = mnt_tmp;
		mnt_tmp = mnt_tmp->next;
		free(mount_point);
	}

	if (ret != 0 && mnt_paths) {
		for (i = 0; i < idx; i++)
			free(mnt_paths[i]);
		free(mnt_paths);
		*mount_paths = NULL;
	}

	return ret;
}

const struct cgroup_library_version *cgroup_version(void)
{
	return &library_version;
}

/**
 * Finds the current cgroup setup mode (legacy/unified/hybrid).
 * Returns unknown of failure and setup mode on success.
 */
enum cg_setup_mode_t cgroup_setup_mode(void)
{
#define CGROUP2_SUPER_MAGIC	0x63677270
#define CGROUP_SUPER_MAGIC	0x27E0EB

	unsigned int cg_setup_mode_bitmask = 0U;
	enum cg_setup_mode_t setup_mode;
	struct statfs cgrp_buf;
	int i, ret = 0;

	if (!cgroup_initialized) {
		return ECGROUPNOTINITIALIZED;
	}

	setup_mode = CGROUP_MODE_UNK;

	pthread_rwlock_wrlock(&cg_mount_table_lock);
	for (i = 0; cg_mount_table[i].name[0] != '\0'; i++) {
		ret = statfs(cg_mount_table[i].mount.path, &cgrp_buf);
		if (ret) {
			setup_mode = CGROUP_MODE_UNK;
			cgroup_err("Failed to get stats of '%s'\n", cg_mount_table[i].mount.path);
			goto out;
		}

		if (cgrp_buf.f_type == CGROUP2_SUPER_MAGIC)
			cg_setup_mode_bitmask |= (1U << 0);
		else if (cgrp_buf.f_type == CGROUP_SUPER_MAGIC)
			cg_setup_mode_bitmask |= (1U << 1);
	}

	if (cg_cgroup_v2_empty_mount_paths)
		cg_setup_mode_bitmask |= (1U << 0);

	if (cg_setup_mode_bitmask & (1U << 0) && cg_setup_mode_bitmask & (1U << 1))
		setup_mode = CGROUP_MODE_HYBRID;
	else if (cg_setup_mode_bitmask & (1U << 0))
		setup_mode = CGROUP_MODE_UNIFIED;
	else if (cg_setup_mode_bitmask & (1U << 1))
		setup_mode = CGROUP_MODE_LEGACY;

out:
	pthread_rwlock_unlock(&cg_mount_table_lock);
	return setup_mode;
}

int cgroup_get_controller_count(struct cgroup *cgroup)
{
	if (!cgroup)
		return -1;

	return cgroup->index;
}

struct cgroup_controller *cgroup_get_controller_by_index(struct cgroup *cgroup, int index)
{
	if (!cgroup)
		return NULL;

	if (index >= cgroup->index)
		return NULL;

	return cgroup->controller[index];
}

char *cgroup_get_controller_name(struct cgroup_controller *controller)
{
	if (!controller)
		return NULL;

	return controller->name;
}
