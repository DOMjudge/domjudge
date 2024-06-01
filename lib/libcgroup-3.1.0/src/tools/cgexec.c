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

#ifndef _GNU_SOURCE
#define _GNU_SOURCE
#endif

#include "tools-common.h"

#include <libcgroup.h>

#include <limits.h>
#include <search.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <getopt.h>
#include <signal.h>
#include <stdio.h>
#include <errno.h>
#include <grp.h>
#include <pwd.h>

#include <sys/mount.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <sys/wait.h>

#define SYSTEMD_IDLE_THREAD	"libcgroup_systemd_idle_thread"

static pid_t find_scope_pid(pid_t pid);
static int write_systemd_unified(const char * const scope_name);
static int is_scope_parsed(const char * const path);

static struct option longopts[] = {
	{"sticky",	no_argument, NULL, 's'},
	{"help",	no_argument, NULL, 'h'},
	{0, 0, 0, 0}
};

static void usage(int status, const char *program_name)
{
	if (status != 0) {
		err("Wrong input parameters, try %s --help for more information.\n", program_name);
		return;
	}

	info("Usage: %s [-h] [-g <controllers>:<path>] [--sticky] ", program_name);
	info("command [arguments] ...\n");
	info("Run the task in given control group(s)\n");
	info("  -g <controllers>:<path>	Control group which should be added\n");
	info("  -h, --help			Display this help\n");
	info("  --sticky			cgred daemon does not ");
	info("change pidlist and children tasks\n");
#ifdef WITH_SYSTEMD
	info("  -b				Ignore default systemd delegate hierarchy\n");
	info("  -r				Replace the default idle_thread spawned ");
	info("for the systemd scope\n");
#endif
}

int main(int argc, char *argv[])
{
	struct cgroup_group_spec *cgroup_list[CG_HIER_MAX];
	int ignore_default_systemd_delegate_slice = 0;
	pid_t scope_pid = -1;
	int child_status = 0;
	int replace_idle = 0;
	int cg_specified = 0;
	int flag_child = 0;
	int i, ret = 0;
	uid_t uid;
	gid_t gid;
	pid_t pid;
	int c;

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
		case 'g':
			ret = parse_cgroup_spec(cgroup_list, optarg, CG_HIER_MAX);
			if (ret) {
				err("cgroup controller and path parsing failed\n");
				exit(EXIT_BADARGS);
			}
			cg_specified = 1;
			break;
		case 's':
			flag_child |= CGROUP_DAEMON_UNCHANGE_CHILDREN;
			break;
		case 'h':
			usage(0, argv[0]);
			exit(0);
		default:
			usage(1, argv[0]);
			exit(EXIT_BADARGS);
		}
	}

	/* Executable name */
	if (!argv[optind]) {
		usage(1, argv[0]);
		exit(EXIT_BADARGS);
	}

	/* Initialize libcg */
	ret = cgroup_init();
	if (ret) {
		err("libcgroup initialization failed: %s\n", cgroup_strerror(ret));
		return ret;
	}

	/* this is false always for disable-systemd */
	if (!ignore_default_systemd_delegate_slice)
		cgroup_set_default_systemd_cgroup();

	/* Just for debugging purposes. */
	uid = geteuid();
	gid = getegid();
	cgroup_dbg("My euid and egid is: %d,%d\n", (int) uid, (int) gid);

	uid = getuid();
	gid = getgid();
	pid = getpid();

	ret = cgroup_register_unchanged_process(pid, flag_child);
	if (ret) {
		err("registration of process failed\n");
		return ret;
	}

	/*
	 * 'cgexec' command file needs the root privilege for executing a
	 * cgroup_register_unchanged_process() by using unix domain socket,
	 * and an euid/egid should be changed to the executing user from a
	 * root user.
	 */
	if (setresuid(uid, uid, uid)) {
		err("%s", strerror(errno));
		return -1;
	}

	if (setresgid(gid, gid, gid)) {
		err("%s", strerror(errno));
		return -1;
	}

	if (cg_specified) {
		/*
		 * User has specified the list of control group
		 * and controllers
		 */
		for (i = 0; i < CG_HIER_MAX; i++) {
			if (!cgroup_list[i])
				break;

			ret = cgroup_change_cgroup_path(cgroup_list[i]->path, pid,
				(const char *const*) cgroup_list[i]->controllers);
			if (ret) {
				err("cgroup change of group failed\n");
				return ret;
			}
		}
	} else {

		/* Change the cgroup by determining the rules based on uid */
		ret = cgroup_change_cgroup_flags(uid, gid, argv[optind], pid, 0);
		if (ret) {
			err("cgroup change of group failed\n");
			return ret;
		}
	}

	if (!replace_idle) {
		/* Now exec the new process */
		execvp(argv[optind], &argv[optind]);
		err("exec failed:%s", strerror(errno));
		return -1;
	}

	scope_pid = find_scope_pid(pid);
	if (scope_pid == -1)
		return -1;

	pid = fork();
	if (pid == -1) {
		err("Fork failed for pid %u:%s\n", pid, strerror(errno));
		return -1;
	}

	/* child process kills the spawned idle_thread */
	if (pid == 0) {
		ret = kill(scope_pid, SIGTERM);
		if (ret) {
			err("Failed to kill pid %u:%s\n", scope_pid, strerror(errno));
			exit(1);
		}

		exit(0);
	}

	wait(&child_status);
	if (WEXITSTATUS(child_status))
		return -1;

	/* Now exec the new process */
	execvp(argv[optind], &argv[optind]);
	err("exec failed:%s", strerror(errno));

	return -1;
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

static pid_t find_scope_pid(pid_t pid)
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


	/* Let's parse the cgroup of the pid, to check if its in one or
	 * more .scopes.
	 */
	snprintf(buffer, FILENAME_MAX, "/proc/%u/cgroup", pid);
	pid_proc_fp = fopen(buffer, "re");
	if (!pid_proc_fp) {
		err("Failed to open: %s\n", buffer);
		return -1;
	}

	while (fgets(buffer, FILENAME_MAX, pid_proc_fp)) {
		memset(ctrl_name, '\0', sizeof(CONTROL_NAMELEN_MAX));

		/* read according to the cgroup mode */
		if (strstr(buffer, "::"))
			ret = sscanf(buffer, "%d::%4096s\n", &idx, cgroup_name);
		else
			ret = sscanf(buffer, "%d:%[^:]:%4096s\n", &idx, ctrl_name, cgroup_name);

		if (ret != 2 && ret != 3) {
			err("Unrecognized cgroup file format: %s\n", buffer);
			goto out;
		}

		if (!is_cgroup_mode_unified()) {
			if (ret == 3 && !strncmp(ctrl_name, "name=systemd", 12)) {
				found_systemd_cgrp = 1;
				continue;
			} else if (ret == 2) {
				found_unified_cgrp = 1;
				continue;
			}
		}

		/* skip if the cgroup path doesn't have systemd scope format */
		if (strstr(cgroup_name, ".scope") == NULL ||
		    strstr(cgroup_name, ".slice") == NULL)
			continue;

		/* skip if we have already searched cgroup for idle_thread */
		if (is_scope_parsed(cgroup_name))
			continue;

		/* cgroup v1 might have shared mount points cpu,cpuacct */
		_ctrl_name = strchr(ctrl_name, ',');
		if (_ctrl_name) {
			size = strlen(ctrl_name) - strlen(_ctrl_name);
			ctrl_name[size] = '\0';
		}

		if (ret == 2)
			ret = cgroup_get_procs(cgroup_name, NULL, &pids, &size);
		else
			ret = cgroup_get_procs(cgroup_name, ctrl_name, &pids, &size);
		if (ret) {
			err("Failed to read cgroup.procs of cgroup: %s\n", cgroup_name + 1);
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
			goto out;
		}
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
		ret = write_systemd_unified(scope_name);
		if (ret)
			scope_pid = -1;
	}

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

static int write_systemd_unified(const char * const scope_name)
{
	char cgroup_procs_path[FILENAME_MAX * 2 + 25];
	FILE *cgroup_systemd_path_f = NULL;
	FILE *cgroup_unified_path_f = NULL;
	char *cgroup_name = NULL;
	pid_t pid;

	/* construct the systemd cgroup path, by parsing /proc/mounts */
	find_mnt_point("name=systemd ", &cgroup_name);
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
		find_mnt_point("unified cgroup2", &cgroup_name);
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

	pid = getpid();

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
