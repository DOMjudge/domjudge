// SPDX-License-Identifier: LGPL-2.1-only
/**
 * Sample program that shows how to use libcgroup to create a systemd scope
 *
 * This program is designed to meet the requirements outlined in the systemd
 * cmdline example [1] via the libcgroup C APIs.
 *
 * [1] https://github.com/libcgroup/libcgroup/blob/main/samples/cmdline/systemd-with-idle-process.md
 *
 * Copyright (c) 2023 Oracle and/or its affiliates.
 * Author: Tom Hromatka <tom.hromatka@oracle.com>
 */

/*
 * To compile and link this program:
 * 	(From the root of the libcgroup source code directory)
 * 	$ ./bootstrap
 * 	$ ./configure --sysconfdir=/etc --localstatedir=/var \
 * 	  --enable-opaque-hierarchy="name=systemd" --enable-systemd \
 * 	  --enable-python --enable-samples
 * 	$ make
 *
 * Add the libcgroup idle thread to your PATH.  (Some distros restrict the
 * modification of the $PATH environment variable when invoking sudo, so you
 * will need to manually copy the executable to your path.)
 * 	$ sudo cp src/libcgroup_systemd_idle_thread /a/path/in/your/sudo/path
 *
 * To run this program:
 *      $ # Note that there are more options.  Run `create_systemd_scope -h` for more info
 * 	$ sudo LD_LIBRARY_PATH=src/.libs ./samples/c/create_systemd_scope \
 * 	  --slice <yourslicename> --scope <yourscopename>
 */

/*
 */

#include <libcgroup.h>
#include <sys/wait.h>
#include <getopt.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <errno.h>

#define TMP_CGNAME "tmp"
#define HIGH_CGNAME "high-priority"
#define MED_CGNAME "medium-priority"
#define LOW_CGNAME "low-priority"

struct example_opts {
	char slice[FILENAME_MAX];
	char scope[FILENAME_MAX];

	struct cgroup_systemd_scope_opts systemd_opts;

	bool debug;
};

static const struct option long_opts[] = {
	{"delegated",		no_argument,	   NULL, 'd'},
	{"help",		no_argument,	   NULL, 'h'},
	{"pid",			required_argument, NULL, 'p'},
	{"scope",		required_argument, NULL, 's'},
	{"slice",		required_argument, NULL, 't'},
	{"verbose",		no_argument,       NULL, 'v'},
	{NULL, 0, NULL, 0}
};

static void usage(const char * const program_name)
{
	printf("Usage: %s -s <scope> -t <slice> [-p <pid>]\n", program_name);
	printf("Create a systemd scope using the libcgroup C APIs\n");
	printf("  -d, --delegated	Instruct systemd to create a delegated scope\n");
	printf("  -p, --pid		PID to be placed in the scope, if not provided,\n");
	printf("			libcgroup will place a default idle PID in the scope\n");
	printf("  -s, --scope		Scope name, must end in .scope\n");
	printf("  -t, --slice		Slice name, must end in .slice\n");
	printf("  -v, --verbose		Enable libcgroup debug messages\n");
}

static int parse_opts(int argc, char *argv[], struct example_opts * const opts)
{
	int c;

	while ((c = getopt_long(argc, argv, "hs:t:p:dv", long_opts, NULL)) > 0) {
		switch (c) {
		case 'd':
			opts->systemd_opts.delegated = true;
			break;
		case 'h':
			usage(argv[0]);
			exit(0);
		case 'p':
			opts->systemd_opts.pid = atoi(optarg);
			if (opts->systemd_opts.pid <= 1) {
				usage(argv[0]);
				exit(1);
			}
			break;
		case 's':
			strncpy(opts->scope, optarg, FILENAME_MAX - 1);
			break;
		case 't':
			strncpy(opts->slice, optarg, FILENAME_MAX - 1);
			break;
		case 'v':
			opts->debug = true;
			break;
		default:
			usage(argv[0]);
			exit(1);
			break;
		}
	}

	return 0;
}

static int create_scope(const struct example_opts * const opts)
{
	int ret;

	printf("\n----------------------------------------------------------------\n");
	printf("Creating systemd scope, %s/%s,\n", opts->slice, opts->scope);
	if (opts->systemd_opts.pid > 1)
		printf("and placing PID, %d, in the scope\n", opts->systemd_opts.pid);
	else
		printf("and libcgroup will place an idle process in the scope\n");
	printf("----------------------------------------------------------------\n\n");

	ret = cgroup_create_scope(opts->scope, opts->slice, &opts->systemd_opts);
	if (ret == ECGINVAL) {
		printf("An invalid parameter was passed into cgroup_create_scope()\n"
		       "Check your scope name, slice name, and systemd options carefully\n");
		goto error;
	} else if (ret == ECGOTHER) {
		printf("Libcgroup typically returns ECGOTHER when a system call fails.\n"
		       "These failures could be caused by being out of memory, lack of\n"
		       "permissions, etc.  Enabling libcgroup debug messages may help\n"
		       "root cause the issue; pass in '-v' to this program\n");
		printf("The failing system call returned an errno of %d\n",
		       cgroup_get_last_errno());
		goto error;
	} else if (ret > 0) {
		printf("An unspecified error occurred - likely in the formation or\n"
		       "sending/receiving of the message to systemd to create the scope.\n"
		       "Ensure that this application has the requisite permissions and\n"
		       "capabilities to create a scope.  Enabling libcgroup debug\n"
		       "messages may help root cause the failing instruction; pass in\n"
		       "'-v' to this program\n");
		printf("Libcgroup returned %d\n", ret);
		goto error;
	}

	/*
	 * Counterintuitively, this function returns 1 on success
	 */
	ret = cgroup_write_systemd_default_cgroup(opts->slice, opts->scope);
	if (ret != 1) {
		printf("Failed to set the libcgroup default scope/slice: %d: %s\n",
		       ret, cgroup_strerror(ret));
		ret = ECGFAIL;
		goto error;
	}

	/*
	 * cgroup_set_default_systemd_cgroup() will return 1 if a default slice/scope are
	 * set, and will return 0 otherwise.  Ensure that a default slice/scope are set.
	 * This is critical because subsequent code in this example is assuming a default,
	 * and if the default is not set, then calls will be operating on the root cgroup.
	 */
	ret = cgroup_set_default_systemd_cgroup();
	if (ret != 1) {
		printf("The default slice/scope are not properly set\n");
		ret = ECGFAIL;
		goto error;
	}

	ret = 0;

error:
	return ret;
}

static int create_tmp_cgroup(const struct example_opts * const opts)
{
	struct cgroup *cg;
	int ret = 0;

	cg = cgroup_new_cgroup(TMP_CGNAME);
	if (!cg) {
		printf("Failed to allocate the cgroup struct.  Are we out of memory?\n");
		goto error;
	}

	ret = cgroup_create_cgroup(cg, 1);
	if (ret) {
		printf("Failed to write the cgroup to /sys/fs/cgroup: %d: %s\n",
		       ret, cgroup_strerror(ret));
		goto error;
	}

error:
	if (cg)
		cgroup_free(&cg);

	return ret;
}

static int move_pids_to_tmp_cgroup(const struct example_opts * const opts)
{
	struct cgroup *cg = NULL;
	int ret, pid_cnt, i;
	pid_t *pids = NULL;
	int saved_ret = 0;

	/*
	 * Since we told libcgroup that our slice and scope are the default, we can
	 * operate with that as our "root" directory.  If we hadn't set them as the
	 * default, then we would have had to build up the relative path -
	 * <slice>/<scope>.
	 */
	ret = cgroup_get_procs("/", NULL, &pids, &pid_cnt);
	if (ret) {
		printf("Failed to get the pids in %s: %d: %s\n", opts->scope,
		       ret, cgroup_strerror(ret));
		goto error;
	}

	cg = cgroup_new_cgroup(TMP_CGNAME);
	if (!cg) {
		printf("Failed to allocate the cgroup struct.  Are we out of memory?\n");
		goto error;
	}

	for (i = 0; i < pid_cnt; i++) {
		ret = cgroup_attach_task_pid(cg, pids[i]);
		if (ret) {
			printf("Failed to attach pid %d to %s\n", pids[i], TMP_CGNAME);
			/*
			 * Instead of failing, let's save off the return code and continue
			 * moving processes to the new cgroup.  Perhaps the failing PID was
			 * killed between when we read cgroup.procs and when we tried to
			 * move it
			 */
			saved_ret = ret;
		}
	}

error:
	if (pids)
		free(pids);

	if (cg)
		cgroup_free(&cg);

	if (ret == 0 && saved_ret)
		ret = saved_ret;

	return ret;
}

static int create_high_priority_cgroup(const struct example_opts * const opts)
{
	struct cgroup_controller *ctrl;
	struct cgroup *cg;
	int ret = 0;

	cg = cgroup_new_cgroup(HIGH_CGNAME);
	if (!cg) {
		printf("Failed to allocate the cgroup struct.  Are we out of memory?\n");
		ret = ECGFAIL;
		goto error;
	}

	ctrl = cgroup_add_controller(cg, "cpu");
	if (!ctrl) {
		printf("Failed to add the cpu controller to the %s cgroup struct\n",
		       HIGH_CGNAME);
		ret = ECGFAIL;
		goto error;
	}

	ret = cgroup_set_value_string(ctrl, "cpu.weight", "600");
	if (ret) {
		printf("Failed to set %s's cpu.weight: %d: %s\n", HIGH_CGNAME, ret,
		       cgroup_strerror(ret));
		goto error;
	}

	ctrl = cgroup_add_controller(cg, "memory");
	if (!ctrl) {
		printf("Failed to add the memory controller to the %s cgroup struct\n",
		       HIGH_CGNAME);
		ret = ECGFAIL;
		goto error;
	}

	ret = cgroup_set_value_string(ctrl, "memory.low", "1G");
	if (ret) {
		printf("Failed to set %s's memory.low: %d: %s\n", HIGH_CGNAME, ret,
		       cgroup_strerror(ret));
		goto error;
	}

	ret = cgroup_create_cgroup(cg, 0);
	if (ret) {
		printf("Failed to write the %s cgroup to /sys/fs/cgroup: %d: %s\n",
		       HIGH_CGNAME, ret, cgroup_strerror(ret));
		goto error;
	}

error:
	if (cg)
		cgroup_free(&cg);

	return ret;
}

static int create_medium_priority_cgroup(const struct example_opts * const opts)
{
	struct cgroup_controller *ctrl;
	struct cgroup *cg;
	int ret = 0;

	cg = cgroup_new_cgroup(MED_CGNAME);
	if (!cg) {
		printf("Failed to allocate the cgroup struct.  Are we out of memory?\n");
		ret = ECGFAIL;
		goto error;
	}

	ctrl = cgroup_add_controller(cg, "cpu");
	if (!ctrl) {
		printf("Failed to add the cpu controller to the %s cgroup struct\n",
		       MED_CGNAME);
		ret = ECGFAIL;
		goto error;
	}

	ret = cgroup_set_value_string(ctrl, "cpu.weight", "300");
	if (ret) {
		printf("Failed to set %s's cpu.weight: %d: %s\n", MED_CGNAME, ret,
		       cgroup_strerror(ret));
		goto error;
	}

	ctrl = cgroup_add_controller(cg, "memory");
	if (!ctrl) {
		printf("Failed to add the memory controller to the %s cgroup struct\n",
		       MED_CGNAME);
		ret = ECGFAIL;
		goto error;
	}

	ret = cgroup_set_value_string(ctrl, "memory.high", "3G");
	if (ret) {
		printf("Failed to set %s's memory.high: %d: %s\n", MED_CGNAME, ret,
		       cgroup_strerror(ret));
		goto error;
	}

	ret = cgroup_create_cgroup(cg, 0);
	if (ret) {
		printf("Failed to write the %s cgroup to /sys/fs/cgroup: %d: %s\n",
		       MED_CGNAME, ret, cgroup_strerror(ret));
		goto error;
	}

error:
	if (cg)
		cgroup_free(&cg);

	return ret;
}

static int create_low_priority_cgroup(const struct example_opts * const opts)
{
	struct cgroup_controller *ctrl;
	struct cgroup *cg;
	int ret = 0;

	cg = cgroup_new_cgroup(LOW_CGNAME);
	if (!cg) {
		printf("Failed to allocate the cgroup struct.  Are we out of memory?\n");
		ret = ECGFAIL;
		goto error;
	}

	ctrl = cgroup_add_controller(cg, "cpu");
	if (!ctrl) {
		printf("Failed to add the cpu controller to the %s cgroup struct\n",
		       LOW_CGNAME);
		ret = ECGFAIL;
		goto error;
	}

	ret = cgroup_set_value_string(ctrl, "cpu.weight", "100");
	if (ret) {
		printf("Failed to set %s's cpu.weight: %d: %s\n", LOW_CGNAME, ret,
		       cgroup_strerror(ret));
		goto error;
	}

	ctrl = cgroup_add_controller(cg, "memory");
	if (!ctrl) {
		printf("Failed to add the memory controller to the %s cgroup struct\n",
		       LOW_CGNAME);
		ret = ECGFAIL;
		goto error;
	}

	ret = cgroup_set_value_string(ctrl, "memory.max", "2G");
	if (ret) {
		printf("Failed to set %s's memory.high: %d: %s\n", LOW_CGNAME, ret,
		       cgroup_strerror(ret));
		goto error;
	}

	ret = cgroup_create_cgroup(cg, 0);
	if (ret) {
		printf("Failed to write the %s cgroup to /sys/fs/cgroup: %d: %s\n",
		       LOW_CGNAME, ret, cgroup_strerror(ret));
		goto error;
	}

error:
	if (cg)
		cgroup_free(&cg);

	return ret;
}

static int create_process(pid_t * const pid, const char * const cgname)
{
	int ret = 0;

	*pid = fork();
	if (*pid < 0) {
		printf("Failed to create the process: %d\n", errno);
		ret = ECGFAIL;
		goto error;
	} else if (*pid == 0) {
		/*
		 * This is the child process.  Move it to the requested cgroup
		 */
		ret = cgroup_change_cgroup_path(cgname, getpid(), NULL);
		if (ret)
			exit(0);

		while (true)
			sleep(10);
	}

error:
	if (ret)
		*pid = -1;

	return ret;
}

static int delete_tmp_cgroup(void)
{
	struct cgroup *cg = NULL;
	int ret, pid_cnt, i;
	int saved_errno = 0;
	pid_t *pids = NULL;

	ret = cgroup_get_procs(TMP_CGNAME, NULL, &pids, &pid_cnt);
	if (ret) {
		printf("Failed to get the pids in %s: %d: %s\n", TMP_CGNAME,
		       ret, cgroup_strerror(ret));
		goto error;
	}

	for (i = 0; i < pid_cnt; i++) {
		ret = kill(pids[i], SIGTERM);
		if (ret < 0) {
			saved_errno = errno;
			printf("Kill failed: %d\n", errno);
			/*
			 * Do not bail out here as there may be other PIDS to process
			 */
		}
	}

	cg = cgroup_new_cgroup(TMP_CGNAME);
	if (!cg) {
		printf("Failed to allocate the cgroup struct.  Are we out of memory?\n");
		goto error;
	}

	ret = cgroup_delete_cgroup(cg, 1);
	if (ret)
		printf("Failed to delete the %s cgroup: %d: %s\n", TMP_CGNAME, ret,
		       cgroup_strerror(ret));

error:
	if (pids)
		free(pids);

	if (cg)
		cgroup_free(&cg);

	if (ret == 0 && saved_errno)
		ret = ECGFAIL;

	return ret;
}

static void wait_for_child(pid_t pid)
{
	int wstatus;

	(void)waitpid(pid, &wstatus, 0);

	if (WIFEXITED(wstatus))
		printf("pid %d exited with status %d\n", pid, WEXITSTATUS(wstatus));
	else if (WIFSIGNALED(wstatus))
		printf("pid %d exited due to signal %d\n", pid, WTERMSIG(wstatus));
}

int main(int argc, char *argv[])
{
	pid_t high_pid, med_pid, low_pid;
	struct example_opts opts = {0};
	int ret = 0;

	if (argc < 3) {
		usage(argv[0]);
		exit(1);
	}

	ret = cgroup_set_default_scope_opts(&opts.systemd_opts);
	if (ret) {
		printf("Failed to set the default systemd options: %d\n", ret);
		goto error;
	}

	ret = parse_opts(argc, argv, &opts);
	if (ret) {
		printf("Failed to parse the command line options: %d\n", ret);
		goto error;
	}

	if (opts.debug)
		cgroup_set_default_logger(CGROUP_LOG_DEBUG);

	ret = cgroup_init();
	if (ret)
		goto error;

	ret = create_scope(&opts);
	if (ret)
		goto error;

	ret = create_tmp_cgroup(&opts);
	if (ret)
		goto error;

	ret = move_pids_to_tmp_cgroup(&opts);
	if (ret)
		goto error;

	ret = create_high_priority_cgroup(&opts);
	if (ret)
		goto error;

	ret = create_medium_priority_cgroup(&opts);
	if (ret)
		goto error;

	ret = create_low_priority_cgroup(&opts);
	if (ret)
		goto error;

	ret = create_process(&high_pid, HIGH_CGNAME);
	if (ret)
		goto error;

	ret = create_process(&med_pid, MED_CGNAME);
	if (ret)
		goto error;

	ret = create_process(&low_pid, LOW_CGNAME);
	if (ret)
		goto error;

	ret = delete_tmp_cgroup();
	if (ret)
		goto error;

	printf("\n----------------------------------------------------------------\n");
	printf("Cgroup setup completed successfully\n");
	printf("\t* The scope %s was placed under slice %s\n", opts.scope, opts.slice);
	printf("\t* Libcgroup initially placed an idle process in the scope,\n"
	       "\t  but it has been removed by this program\n");
	printf("\t* PID %d has been placed in the %s cgroup\n", high_pid, HIGH_CGNAME);
	printf("\t* PID %d has been placed in the %s cgroup\n", med_pid, MED_CGNAME);
	printf("\t* PID %d has been placed in the %s cgroup\n", low_pid, LOW_CGNAME);
	printf("\nThis program will wait for the aforementioned child processes to\n"
	       "exit before exiting itself. Systemd will automatically delete the\n"
	       "scope when there are no longer any processes running within the.\n"
	       "scope.  Systemd will not automatically delete the slice.\n");
	printf("----------------------------------------------------------------\n\n");

	wait_for_child(high_pid);
	wait_for_child(med_pid);
	wait_for_child(low_pid);

error:
	return ret;
}
