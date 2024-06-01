// SPDX-License-Identifier: LGPL-2.1-only
/**
 * Copyright RedHat Inc. 2008
 *
 * Authors:	Vivek Goyal <vgoyal@redhat.com>
 *
 * Replace systemd idle_thread enhancements by Kamalesh Babulal
 * Copyright (c) 2023 Oracle and/or its affiliates.
 * Author: Kamalesh Babulal <kamalesh.babulal@oracle.com>
 */

#include "tools-common.h"

#include <libcgroup.h>
#include <libcgroup-internal.h>

#include <stdlib.h>
#include <string.h>
#include <limits.h>
#include <unistd.h>
#include <getopt.h>
#include <signal.h>
#include <errno.h>
#include <stdio.h>
#include <pwd.h>

#include <sys/mount.h>
#include <sys/types.h>
#include <sys/stat.h>


#define TEMP_BUF	81

#define SYSTEMD_IDLE_THREAD	"libcgroup_systemd_idle_thread"

static pid_t find_scope_pid(pid_t pid, int capture);
static int write_systemd_unified(const char * const cgrp_name, pid_t pid);
static int is_scope_parsed(const char * const path);
static int rollback_pid_cgroups(pid_t pid);

struct cgroup_info {
	char ctrl_name[CONTROL_NAMELEN_MAX];
	char cgrp_path[FILENAME_MAX];
}info[MAX_MNT_ELEMENTS + 1];

static void usage(int status, const char *program_name)
{
	if (status != 0) {
		err("Wrong input parameters, try %s '-h' for more information.\n", program_name);
		return;
	}

	info("Usage: %s [[-g] <controllers>:<path>] ", program_name);
	info("[--sticky | --cancel-sticky] <list of pids>\n");
	info("Move running task(s) to given cgroups\n");
	info("  -h, --help			Display this help\n");
	info("  -g <controllers>:<path>	Control group to be used as target\n");
	info("  --cancel-sticky		cgred daemon change pidlist and children tasks\n");
	info("  --sticky			cgred daemon does not change ");
	info("pidlist and children tasks\n");
#ifdef WITH_SYSTEMD
	info("  -b				Ignore default systemd delegate hierarchy\n");
	info("  -r				Replace the default idle_thread spawned ");
	info("for the systemd scope\n");
#endif
}

/*
 * Change process group as specified on command line.
 */
static int change_group_path(pid_t pid, struct cgroup_group_spec *cgroup_list[])
{
	int ret = 0;
	int i;

	for (i = 0; i < CG_HIER_MAX; i++) {
		if (!cgroup_list[i])
			break;

		ret = cgroup_change_cgroup_path(cgroup_list[i]->path, pid,
						(const char *const*) cgroup_list[i]->controllers);
		if (ret) {
			err("Error changing group of pid %d: %s\n", pid, cgroup_strerror(ret));
			return -1;
		}
	}

	return 0;
}

/*
 * Change process group as specified in cgrules.conf.
 */
static int change_group_based_on_rule(pid_t pid)
{
	char *procname = NULL;
	int ret = -1;
	uid_t euid;
	gid_t egid;

	/* Put pid into right cgroup as per rules in /etc/cgrules.conf */
	if (cgroup_get_uid_gid_from_procfs(pid, &euid, &egid)) {
		err("Error in determining euid/egid of pid %d\n", pid);
		goto out;
	}

	ret = cgroup_get_procname_from_procfs(pid, &procname);
	if (ret) {
		err("Error in determining process name of pid %d\n", pid);
		goto out;
	}

	/* Change the cgroup by determining the rules */
	ret = cgroup_change_cgroup_flags(euid, egid, procname, pid, 0);
	if (ret) {
		err("Error: change of cgroup failed for pid %d: %s\n", pid, cgroup_strerror(ret));
		goto out;
	}
	ret = 0;

out:
	if (procname)
		free(procname);

	return ret;
}

static struct option longopts[] = {
	{"sticky",		no_argument, NULL, 's'},
	{"cancel-sticky",	no_argument, NULL, 'u'},
	{"help",		no_argument, NULL, 'h'},
	{0, 0, 0, 0}
};

int main(int argc, char *argv[])
{
	struct cgroup_group_spec *cgroup_list[CG_HIER_MAX];
	int ignore_default_systemd_delegate_slice = 0;
	int ret = 0, i, exit_code = 0;
	int skip_replace_idle = 0;
	pid_t scope_pid = -1;
	int replace_idle = 0;
	int cg_specified = 0;
	int flag = 0;
	char *endptr;
	pid_t pid;
	int c;

	if (argc < 2) {
		usage(1, argv[0]);
		exit(EXIT_BADARGS);
	}

	memset(cgroup_list, 0, sizeof(cgroup_list));
#ifdef WITH_SYSTEMD
	while ((c = getopt_long(argc, argv, "+g:shbr", longopts, NULL)) > 0) {
		switch (c) {
		case 'b':
			ignore_default_systemd_delegate_slice = 1;
			break;
		case 'r':
			replace_idle = 1;
			break;
#else
	while ((c = getopt_long(argc, argv, "+g:sh", longopts, NULL)) > 0) {
		switch (c) {
#endif
		case 'h':
			usage(0, argv[0]);
			exit(0);
			break;
		case 'g':
			ret = parse_cgroup_spec(cgroup_list, optarg, CG_HIER_MAX);
			if (ret) {
				err("cgroup controller and path parsing failed\n");
				exit(EXIT_BADARGS);
			}
			cg_specified = 1;
			break;
		case 's':
			flag |= CGROUP_DAEMON_UNCHANGE_CHILDREN;
			break;
		case 'u':
			flag |= CGROUP_DAEMON_CANCEL_UNCHANGE_PROCESS;
			break;
		default:
			usage(1, argv[0]);
			exit(EXIT_BADARGS);
			break;
		}
	}

	/* Initialize libcg */
	ret = cgroup_init();
	if (ret) {
		err("%s: libcgroup initialization failed: %s\n", argv[0], cgroup_strerror(ret));
		return ret;
	}

	/* this is false always for disable-systemd */
	if (!ignore_default_systemd_delegate_slice)
		cgroup_set_default_systemd_cgroup();

	for (i = optind; i < argc; i++) {
		pid = (pid_t) strtol(argv[i], &endptr, 10);
		if (endptr[0] != '\0') {
			/* the input argument was not a number */
			err("Error: %s is not valid pid.\n", argv[i]);
			exit_code = 2;
			continue;
		}

		if (flag)
			ret = cgroup_register_unchanged_process(pid, flag);
		if (ret)
			exit_code = 1;

		if (replace_idle && !skip_replace_idle) {
			ret = find_scope_pid(pid, 1);
			if (ret) {
				err("Failed to read /proc/%u/cgroups\n", pid);
				return 1;
			}
		}

		if (cg_specified)
			ret = change_group_path(pid, cgroup_list);
		else
			ret = change_group_based_on_rule(pid);

		/* if any group change fails */
		if (ret)
			exit_code = 1;

		/* skip replacing of idle_thread in systemd slice */
		if (!replace_idle)
			continue;

		/* systemd idle_thread is already replaced */
		if (skip_replace_idle)
			continue;

		scope_pid = find_scope_pid(pid, 0);
		if (scope_pid == -1)
			goto err;

		skip_replace_idle = 1;

		ret = kill(scope_pid, SIGTERM);
		if (ret) {
			err("Failed to kill pid %u:%s\n", scope_pid, strerror(errno));
			goto err;
		}
	}

	return exit_code;

err:
	exit_code = rollback_pid_cgroups(pid);
	return exit_code;
}

static pid_t search_systemd_idle_thread_task(pid_t pids[], size_t size)
{
	char task_cmd[FILENAME_MAX];
	char buffer[FILENAME_MAX];
	FILE *pid_cmd_fp = NULL;
	int scope_pid = -1;
	int i;

	for (i = 0; i < size; i++) {
		snprintf(buffer, FILENAME_MAX, "/proc/%u/cmdline", pids[i]);
		pid_cmd_fp = fopen(buffer, "re");
		/* task might have exited */
		if (!pid_cmd_fp)
			continue;

		/* task might have exited, so consider only successful reads. */
		if (fgets(task_cmd, FILENAME_MAX, pid_cmd_fp)) {
			if (!strcmp(task_cmd, SYSTEMD_IDLE_THREAD)) {
				scope_pid = pids[i];
				fclose(pid_cmd_fp);
				break;
			}
		}
		fclose(pid_cmd_fp);
	}

	return scope_pid;
}

static pid_t find_scope_pid(pid_t pid, int capture)
{
	pid_t _scope_pid = -1, scope_pid = -1;
	char ctrl_name[CONTROL_NAMELEN_MAX];
	char cgroup_name[FILENAME_MAX];
	char scope_name[FILENAME_MAX];
	int found_systemd_cgrp = 0;
	int found_unified_cgrp = 0;
	char buffer[FILENAME_MAX];
	FILE *pid_proc_fp = NULL;
	char *_ctrl_name = NULL;
	int idx, ret, size = 0;
	pid_t *pids;
	int i=0;

	/*
	 * Let's parse the cgroup of the pid, to check if its in one or
	 * more .scopes.
	 */
	snprintf(buffer, FILENAME_MAX, "/proc/%u/cgroup", pid);
	pid_proc_fp = fopen(buffer, "re");
	if (!pid_proc_fp) {
		err("Failed to open: %s\n", buffer);
		return -1;
	}

	while (fgets(buffer, FILENAME_MAX, pid_proc_fp)) {
		memset(ctrl_name, '\0', sizeof(ctrl_name));

		/* check for overflow of controllers */
		if (i >= MAX_MNT_ELEMENTS) {
			err("Found more than MAX_MNT_ELEMENTS controllers\n");
			scope_pid = -1;
			goto out;
		}

		/* read according to the cgroup mode */
		if (strstr(buffer, "::")) {
			snprintf(ctrl_name, CONTROL_NAMELEN_MAX, "unified");
			ret = sscanf(buffer, "%d::%4096s\n", &idx, cgroup_name);
		} else{
			ret = sscanf(buffer, "%d:%[^:]:%4096s\n", &idx, ctrl_name, cgroup_name);
		}

		if (ret != 2 && ret != 3) {
			err("Unrecognized cgroup file format: %s\n", buffer);
			scope_pid = -1;
			goto out;
		}

		/* cgroup v1 might have shared mount points cpu,cpuacct */
		_ctrl_name = strchr(ctrl_name, ',');
		if (_ctrl_name) {
			size = strlen(ctrl_name) - strlen(_ctrl_name);
			ctrl_name[size] = '\0';
		}

		/*
		 * capture is true, while the pid's controller and cgroups
		 * are populated for rollback case.
		 */
		if (capture) {
			snprintf(info[i].ctrl_name, CONTROL_NAMELEN_MAX, "%s", ctrl_name);
			snprintf(info[i].cgrp_path, FILENAME_MAX, "%s", cgroup_name);
		}

		if (!is_cgroup_mode_unified()) {
			if (ret == 3 && !strncmp(ctrl_name, "name=", 5)) {
				if (!strcmp(ctrl_name, "name=systemd")) {
					i++;
					found_systemd_cgrp = 1;
				}
				continue;
			} else if (ret == 2) {
				i++;
				found_unified_cgrp = 1;
				continue;
			}
		}
		i++;

		/* we are not interested in other functionality */
		if (capture)
			continue;

		/* skip if the cgroup path doesn't have systemd scope format */
		if (strstr(cgroup_name, ".scope") == NULL ||
		    strstr(cgroup_name, ".slice") == NULL)
			continue;

		/* skip if we have already searched cgroup for idle_thread */
		if (is_scope_parsed(cgroup_name))
			continue;


		if (ret == 2)
			ret = cgroup_get_procs(cgroup_name, NULL, &pids, &size);
		else
			ret = cgroup_get_procs(cgroup_name, ctrl_name, &pids, &size);
		if (ret) {
			err("Failed to read cgroup.procs of cgroup: %s\n", cgroup_name + 1);
			scope_pid = -1;
			goto out;
		}

		/*
		 * .scope created by the non-libcgroup process, will not
		 * have libcgroup_systemd_idle_thread
		 */
		_scope_pid = search_systemd_idle_thread_task(pids, size);
		free(pids);

		if (_scope_pid == -1)
			continue;

		if (scope_pid == -1) {
			/*
			 * cgexec pid needs to written into:
			 * ../systemd/<slice>/<scope>/cgroup.procs (legacy/hybrid)
			 * ../unified/<slice>/<scope>/cgroup.procs (hybrid)
			 */
			snprintf(scope_name, FILENAME_MAX, "%s", cgroup_name);
			scope_pid = _scope_pid;
			continue;
		}

		if (_scope_pid != scope_pid) {
			err("Failed to replace scope idle_thread, found two idle_thread\n");
			err(" %u %u\n", scope_pid, _scope_pid);
			scope_pid = -1;
			goto out;
		}
	}

	if (capture) {
		scope_pid = 0;
		goto out;
	}

	if (scope_pid == -1) {
		err("Failed to find idle_thread task\n");
		goto out;
	}

	if (is_cgroup_mode_legacy() && (found_systemd_cgrp == 0 || found_unified_cgrp == 1)) {
		err("cgroup legacy setup incorrect\n");
		scope_pid = -1;
		goto out;
	}

	if (is_cgroup_mode_hybrid() && (found_systemd_cgrp == 0 || found_unified_cgrp == 0)) {
		err("cgroup hybrid setup incorrect\n");
		scope_pid = -1;
		goto out;
	}

	/* This is true for cgroup v1 (legacy/hybrid) */
	if (found_systemd_cgrp) {
		ret = write_systemd_unified(scope_name, pid);
		if (ret)
			scope_pid = -1;
	}

	info[i].ctrl_name[0] = '\0';
out:
	if (pid_proc_fp)
		fclose(pid_proc_fp);

	return scope_pid;
}

/*
 * Parse the /proc/mounts file and look for the controller string
 * in each line. If found copies the mount point into mnt_point,
 * else return NULL mnt_point.
 */
static void find_mnt_point(const char * const controller, char **mnt_point)
{
	char proc_mount[] = "/proc/mounts";
	char cgroup_path[FILENAME_MAX];
	char buffer[FILENAME_MAX * 2];
	FILE *proc_mount_f = NULL;
	int ret;

	*mnt_point = NULL;

	proc_mount_f = fopen(proc_mount, "re");
	if (proc_mount_f == NULL) {
		err("Failed to read %s:%s\n", proc_mount, strerror(errno));
		goto out;
	}

	while (fgets(buffer, (FILENAME_MAX * 2), proc_mount_f) != NULL) {
		/* skip line that doesn't have controller */
		if (!strstr(buffer, controller))
			continue;

		if (strcmp(controller, "name=systemd") == 0) {
			if (!strstr(buffer, "name=systemd ") &&
			    !strstr(buffer, "name=systemd,"))
				continue;
		}

		ret = sscanf(buffer, "%*s %4096s\n", cgroup_path);
		if (ret != 1) {
			err("Failed during read of %s:%s\n", proc_mount, strerror(errno));
			goto out;
		}

		*mnt_point = strdup(cgroup_path);
		if (!*mnt_point)
			err("strdup of %s failed\n", cgroup_path);
		break;
	}

out:
	if (proc_mount_f)
		fclose(proc_mount_f);
}

static int write_systemd_unified(const char * const scope_name, pid_t pid)
{
	char cgroup_procs_path[FILENAME_MAX + 14];
	FILE *cgroup_systemd_path_f = NULL;
	FILE *cgroup_unified_path_f = NULL;
	char *cgroup_name = NULL;

	/* construct the systemd cgroup path, by parsing /proc/mounts */
	find_mnt_point("name=systemd", &cgroup_name);
	if (!cgroup_name) {
		err("Unable find name=systemd cgroup path\n");
		return -1;
	}

	snprintf(cgroup_procs_path, sizeof(cgroup_procs_path), "%s/%s/cgroup.procs",
		 cgroup_name, scope_name);
	free(cgroup_name);

	cgroup_systemd_path_f = fopen(cgroup_procs_path, "we");
	if (!cgroup_systemd_path_f) {
		err("Failed to open %s\n", cgroup_procs_path);
		return -1;
	}

	if (is_cgroup_mode_hybrid()) {
		/*
		 * construct the unified cgroup path, by parsing
		 * /proc/mounts
		 */
		find_mnt_point("unified", &cgroup_name);
		if (!cgroup_name) {
			err("Unable find unified cgroup path\n");
			fclose(cgroup_systemd_path_f);
			return -1;
		}

		snprintf(cgroup_procs_path, sizeof(cgroup_procs_path), "%s/%s/cgroup.procs",
			 cgroup_name, scope_name);
		free(cgroup_name);

		cgroup_unified_path_f = fopen(cgroup_procs_path, "we");
		if (!cgroup_unified_path_f) {
			err("Failed to open %s\n", cgroup_procs_path);
			fclose(cgroup_systemd_path_f);
			return -1;
		}
	}

	fprintf(cgroup_systemd_path_f, "%d", pid);
	fflush(cgroup_systemd_path_f);
	fclose(cgroup_systemd_path_f);

	if (!is_cgroup_mode_hybrid())
		return 0;

	fprintf(cgroup_unified_path_f, "%d", pid);
	fflush(cgroup_unified_path_f);
	fclose(cgroup_unified_path_f);

	return 0;
}

static int is_scope_parsed(const char * const path)
{
	/*
	 * As per, <kernel sources>/kernel/cgroup/cgroup.c::cgroup_init()
	 * At the max there can be only 16 controllers and we are
	 * not accounting for named hierarchies, which can be more
	 * than 16 themselves.
	 */
	static char parsed_scope_path[MAX_MNT_ELEMENTS][FILENAME_MAX];
	int i;

	for (i = 0; i < MAX_MNT_ELEMENTS; i++) {
		if (!strcmp(parsed_scope_path[i], path))
			return 1;
		if (parsed_scope_path[i][0] == '\0') {
			snprintf(parsed_scope_path[i], FILENAME_MAX, "%s", path);
			break;
		}
	}

	return 0;
}

/* Borrowed from src/api.c::__attach_task_pid */
static int attach_task_pid(char *path, pid_t tid)
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
		ret = ECGOTHER;
		goto err;
	}
	ret = fflush(tasks);
	if (ret) {
		ret = ECGOTHER;
		goto err;
	}
	fclose(tasks);
	return 0;
err:
	err("cannot write tid %d to %s:%s\n", tid, path, strerror(errno));
	if (tasks)
		fclose(tasks);
	return ret;
}

static int rollback_pid_cgroups(pid_t pid)
{
	char cgroup_proc_path[FILENAME_MAX + 14];
	char cgroup_path[FILENAME_MAX];
	int err = 0, idx = 0, ret = 0;
	char *cgrp_proc_path = NULL;

	/*
	 * unified cgroup rollback is simple, we need to write into
	 * single cgroup hierarchy.
	 */
	if (is_cgroup_mode_unified()) {
		pthread_rwlock_rdlock(&cg_mount_table_lock);
		cg_build_path_locked(info[idx].cgrp_path, cgroup_path, NULL);
		pthread_rwlock_unlock(&cg_mount_table_lock);

		snprintf(cgroup_proc_path, FILENAME_MAX + 14, "%s/cgroup.procs", cgroup_path);
		ret = attach_task_pid(cgroup_proc_path, pid);
		return ret;
	}

	for (idx = 0; info[idx].ctrl_name[0] != '\0'; idx++) {
		/* find the systemd cgroup path */
		if (!strcmp(info[idx].ctrl_name, "name=systemd")) {
			find_mnt_point("name=systemd", &cgrp_proc_path);
			if (!cgrp_proc_path) {
				err("Unable find name=systemd cgroup path\n");
				return -1;
			}

			snprintf(cgroup_proc_path, FILENAME_MAX + 14, "%s/%s/cgroup.procs",
				 cgrp_proc_path, info[idx].cgrp_path);
			free(cgrp_proc_path);

		/* find the unified cgroup path */
		} else if (is_cgroup_mode_hybrid() &&
			   !strcmp(info[idx].ctrl_name, "unified")) {
			find_mnt_point("unified cgroup2", &cgrp_proc_path);
			if (!cgrp_proc_path) {
				err("Unable find unified cgroup path\n");
				return -1;
			}

			snprintf(cgroup_proc_path, FILENAME_MAX + 14, "%s/%s/cgroup.procs",
				cgrp_proc_path, info[idx].cgrp_path);
			free(cgrp_proc_path);

		/* find other controller hierarchy path */
		} else {
			pthread_rwlock_rdlock(&cg_mount_table_lock);
			cg_build_path_locked(info[idx].cgrp_path, cgroup_path, info[idx].ctrl_name);
			pthread_rwlock_unlock(&cg_mount_table_lock);

			snprintf(cgroup_proc_path, FILENAME_MAX + 14, "%s/cgroup.procs",
				cgroup_path);
		}

		/* record the error and continue */
		ret = attach_task_pid(cgroup_proc_path, pid);
		if (ret)
			err = -1;
	}

	return err;
}
