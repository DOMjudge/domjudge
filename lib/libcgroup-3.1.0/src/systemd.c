/* SPDX-License-Identifier: LGPL-2.1-only */
/**
 * Copyright (c) 2022 Oracle and/or its affiliates.
 * Author: Tom Hromatka <tom.hromatka@oracle.com>
 * Author: Silvia Chapa <silvia.chapa@oracle.com>
 */

#include <libcgroup-internal.h>
#include <systemd/sd-bus.h>
#include <libcgroup.h>
#include <unistd.h>
#include <assert.h>
#include <stdlib.h>
#include <libgen.h>
#include <errno.h>

#define USEC_PER_SEC 1000000

static const char * const modes[] = {
	"fail",			/* CGROUP_SYSTEMD_MODE_FAIL */
	"replace",		/* CGROUP_SYSTEMD_MODE_REPLACE */
	"isolate",		/* CGROUP_SYSTEMD_MODE_ISOLATE */
	"ignore-dependencies",	/* CGROUP_SYSTEMD_MODE_IGNORE_DEPS */
	"ignore-requirements",	/* CGROUP_SYSTEMD_MODE_IGNORE_REQS */
};
static_assert((sizeof(modes) / sizeof(modes[0])) == CGROUP_SYSTEMD_MODE_CNT,
	      "modes[] array must be same length as CGROUP_SYSTEMD_MODE_CNT");

static const char * const sender = "org.freedesktop.systemd1";
static const char * const path = "/org/freedesktop/systemd1";
static const char * const interface = "org.freedesktop.systemd1.Manager";

int cgroup_set_default_scope_opts(struct cgroup_systemd_scope_opts * const opts)
{
	if (!opts)
		return ECGINVAL;

	opts->delegated = 1;
	opts->mode = CGROUP_SYSTEMD_MODE_FAIL;
	opts->pid = -1;

	return 0;
}

/*
 * Returns time elapsed in usec
 *
 * Inspired-by: https://github.com/cockpit-project/cockpit/blob/main/src/tls/socket-io.c#L39
 */
static int64_t elapsed_time(const struct timespec * const start, const struct timespec * const end)
{
	int64_t elapsed = (end->tv_sec - start->tv_sec) * 1000000 +
			  (end->tv_nsec - start->tv_nsec) / 1000;

	assert(elapsed >= 0);

	return elapsed;
}

static int job_removed_callback(sd_bus_message *message, void *user_data, sd_bus_error *error)
{
	const char *result, *msg_path, *scope_name;
	const char **job_path = user_data;
	int ret;

	ret = sd_bus_message_read(message, "uoss", NULL, &msg_path, &scope_name, &result);
	if (ret < 0) {
		cgroup_err("callback message read failed: %d\n", errno);
		return 0;
	}

	if (*job_path == NULL || strcmp(msg_path, *job_path) != 0) {
		cgroup_dbg("Received a systemd signal, but it was not our message\n");
		return 0;
	}

	cgroup_dbg("Received JobRemoved signal for scope %s.  Result: %s\n", scope_name, result);

	/*
	 * Use the job_path pointer as a way to inform the original thread that the job has
	 * completed.
	 */
	*job_path = NULL;
	return 0;
}

int cgroup_create_scope(const char * const scope_name, const char * const slice_name,
			const struct cgroup_systemd_scope_opts * const opts)
{
	sd_bus_message *msg = NULL, *reply = NULL;
	int ret = 0, sdret = 0, cgret = ECGFAIL;
	sd_bus_error error = SD_BUS_ERROR_NULL;
	const char *job_path = NULL;
	struct timespec start, now;
	sd_bus *bus = NULL;
	pid_t child_pid;

	if (!scope_name || !slice_name || !opts)
		return ECGINVAL;

	if (strcmp(&scope_name[strlen(scope_name) - strlen(".scope")], ".scope") != 0)
		cgroup_warn("scope doesn't have expected suffix\n");
	if (strcmp(&slice_name[strlen(slice_name) - strlen(".slice")], ".slice") != 0)
		cgroup_warn("slice doesn't have expected suffix\n");

	if (opts->mode >= CGROUP_SYSTEMD_MODE_CNT) {
		cgroup_err("invalid systemd mode: %d\n", opts->mode);
		return ECGINVAL;
	}

	if (opts->mode == CGROUP_SYSTEMD_MODE_ISOLATE ||
	    opts->mode == CGROUP_SYSTEMD_MODE_IGNORE_DEPS ||
	    opts->mode == CGROUP_SYSTEMD_MODE_IGNORE_REQS) {
		cgroup_err("unsupported systemd mode: %d\n", opts->mode);
		return ECGINVAL;
	}

	if (opts->pid < 0) {
		child_pid = fork();
		if (child_pid < 0) {
			last_errno = errno;
			cgroup_err("fork failed: %d\n", errno);
			return ECGOTHER;
		}

		if (child_pid == 0) {
			char *args[] = {"libcgroup_systemd_idle_thread", NULL};

			/*
			 * Have the child sleep forever.  Systemd will delete the scope if
			 * there isn't a running process in it.
			 */
			execvp("libcgroup_systemd_idle_thread", args);

			/* The child process should never get here */
			last_errno = errno;
			cgroup_err("failed to create system idle thread.\n");
			return ECGOTHER;
		}

		cgroup_dbg("created libcgroup_system_idle thread pid %d\n", child_pid);
	} else {
		child_pid = opts->pid;
	}
	cgroup_dbg("pid %d will be placed in scope %s\n", child_pid, scope_name);

	sdret = sd_bus_default_system(&bus);
	if (sdret < 0) {
		cgroup_err("failed to open the system bus: %d\n", errno);
		goto out;
	}

	sdret = sd_bus_match_signal(bus, NULL, sender, path, interface,
				    "JobRemoved", job_removed_callback, &job_path);
	if (sdret < 0) {
		cgroup_err("failed to install match callback: %d\n", errno);
		goto out;
	}

	sdret = sd_bus_message_new_method_call(bus, &msg, sender, path, interface,
					       "StartTransientUnit");
	if (sdret < 0) {
		cgroup_err("failed to create the systemd msg: %d\n", errno);
		goto out;
	}

	sdret = sd_bus_message_append(msg, "ss", scope_name, modes[opts->mode]);
	if (sdret < 0) {
		cgroup_err("failed to append the scope name: %d\n", errno);
		goto out;
	}

	sdret = sd_bus_message_open_container(msg, 'a', "(sv)");
	if (sdret < 0) {
		cgroup_err("failed to open container: %d\n", errno);
		goto out;
	}

	sdret = sd_bus_message_append(msg, "(sv)", "Description", "s",
				      "scope created by libcgroup");
	if (sdret < 0) {
		cgroup_err("failed to append the description: %d\n", errno);
		goto out;
	}

	sdret = sd_bus_message_append(msg, "(sv)", "PIDs", "au", 1, child_pid);
	if (sdret < 0) {
		cgroup_err("failed to append the PID: %d\n", errno);
		goto out;
	}

	sdret = sd_bus_message_append(msg, "(sv)", "Slice", "s", slice_name);
	if (sdret < 0) {
		cgroup_err("failed to append the slice: %d\n", errno);
		goto out;
	}

	if (opts->delegated == 1) {
		sdret = sd_bus_message_append(msg, "(sv)", "Delegate", "b", 1);
		if (sdret < 0) {
			cgroup_err("failed to append delegate: %d\n", errno);
			goto out;
		}
	}

	sdret = sd_bus_message_close_container(msg);
	if (sdret < 0) {
		cgroup_err("failed to close the container: %d\n", errno);
		goto out;
	}

	sdret = sd_bus_message_append(msg, "a(sa(sv))", 0);
	if (sdret < 0) {
		cgroup_err("failed to append aux structure: %d\n", errno);
		goto out;
	}

	sdret = sd_bus_call(bus, msg, 0, &error, &reply);
	if (sdret < 0) {
		cgroup_err("sd_bus_call() failed: %d\n",
			   sd_bus_message_get_errno(msg));
		cgroup_err("error message: %s\n", error.message);
		goto out;
	}

	/* Receive the job_path from systemd */
	sdret = sd_bus_message_read(reply, "o", &job_path);
	if (sdret < 0) {
		cgroup_err("failed to read reply: %d\n", errno);
		goto out;
	}

	cgroup_dbg("job_path = %s\n", job_path);

	ret = clock_gettime(CLOCK_MONOTONIC, &start);
	if (ret < 0) {
		last_errno = errno;
		cgroup_err("Failed to get time: %d\n", errno);
		cgret = ECGOTHER;
		goto out;
	}

	/* The callback will null out the job_path pointer on completion */
	while(job_path) {
		sdret = sd_bus_process(bus, NULL);
		if (sdret < 0) {
			cgroup_err("failed to process the sd bus: %d\n", errno);
			goto out;
		}

		if (sdret == 0) {
			/*
			 * Per the sd_bus_wait() man page, call this function after sd_bus_process
			 * returns zero. The wait time (usec) was somewhat arbitrarily chosen
			 */
			sdret = sd_bus_wait(bus, 10);
			if (sdret < 0) {
				cgroup_err("failed to wait for sd bus: %d\n", errno);
				goto out;
			}
		}

		ret = clock_gettime(CLOCK_MONOTONIC, &now);
		if (ret < 0) {
			last_errno = errno;
			cgroup_err("Failed to get time: %d\n", errno);
			cgret = ECGOTHER;
			goto out;
		}

		if (elapsed_time(&start, &now) > USEC_PER_SEC) {
			cgroup_err("The create scope command timed out\n");
			goto out;
		}
	}

	cgret = 0;

out:
	if (cgret && opts->pid < 0)
		kill(child_pid, SIGTERM);

	sd_bus_error_free(&error);
	sd_bus_message_unref(msg);
	sd_bus_message_unref(reply);
	sd_bus_unref(bus);

	return cgret;
}

int cgroup_create_scope2(struct cgroup *cgroup, int ignore_ownership,
			 const struct cgroup_systemd_scope_opts * const opts)
{
	char *copy1 = NULL, *copy2 = NULL, *slash, *slice_name, *scope_name;
	int ret = 0;

	if (!cgroup)
		return ECGROUPNOTALLOWED;

	slash = strstr(cgroup->name, "/");
	if (!slash) {
		cgroup_err("cgroup name does not contain a slash: %s\n", cgroup->name);
		return ECGINVAL;
	}

	slash = strstr(slash + 1, "/");
	if (slash) {
		cgroup_err("cgroup name contains more than one slash: %s\n", cgroup->name);
		return ECGINVAL;
	}

	copy1 = strdup(cgroup->name);
	if (!copy1) {
		last_errno = errno;
		ret = ECGOTHER;
		goto err;
	}

	scope_name = basename(copy1);

	copy2 = strdup(cgroup->name);
	if (!copy2) {
		last_errno = errno;
		ret = ECGOTHER;
		goto err;
	}

	slice_name = dirname(copy2);

	ret = cgroup_create_scope(scope_name, slice_name, opts);
	if (ret)
		goto err;

	/*
	 * Utilize cgroup_create_cgroup() to assign the requested owner/group and permissions.
	 * cgroup_create_cgroup() can gracefully handle EEXIST if the cgroup already exists, so
	 * we can reuse its ownership logic without penalty.
	 */
	ret = cgroup_create_cgroup(cgroup, ignore_ownership);

err:
	if (copy1)
		free(copy1);
	if (copy2)
		free(copy2);

	return ret;
}
