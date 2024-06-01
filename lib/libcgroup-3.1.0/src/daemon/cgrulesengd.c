// SPDX-License-Identifier: LGPL-2.1-only
/**
 * Copyright Red Hat Inc. 2008
 *
 * Author: Steve Olivieri <sjo@redhat.com>
 * Author: Vivek Goyal <vgoyal@redhat.com>
 *
 * Some part of the programs have been derived from Dhaval Giani's posting
 * for daemon to place the task in right container. Original copyright notice
 * follows.
 *
 * Copyright IBM Corporation, 2007
 * Author: Dhaval Giani <dhaval <at> linux.vnet.ibm.com>
 * Derived from test_cn_proc.c by Matt Helsley
 * Original copyright notice follows
 *
 * Copyright (C) Matt Helsley, IBM Corp. 2005
 * Derived from fcctl.c by Guillaume Thouvenin
 * Original copyright notice follows:
 *
 * Copyright (C) 2005 BULL SA.
 * Written by Guillaume Thouvenin <guillaume.thouvenin <at> bull.net>
 *
 * TODO Stop using netlink for communication (or at least rewrite that part).
 */

#include "../libcgroup-internal.h"
#include "../tools/tools-common.h"
#include "cgrulesengd.h"
#include "libcgroup.h"

#include <stdarg.h>
#include <stdlib.h>
#include <string.h>
#include <signal.h>
#include <syslog.h>
#include <getopt.h>
#include <unistd.h>
#include <errno.h>
#include <stdio.h>
#include <time.h>

#include <pwd.h>
#include <grp.h>

#include <sys/socket.h>
#include <sys/syslog.h>
#include <sys/types.h>
#include <sys/stat.h>

#include <linux/connector.h>
#include <linux/cn_proc.h>
#include <linux/netlink.h>
#include <linux/un.h>

#define NUM_PER_REALLOCATIOM	(100)

/* list of config files from CGCONFIG_CONF_FILE and CGCONFIG_CONF_DIR */
static struct cgroup_string_list template_files;

/* Log file, NULL if logging to file is disabled */
FILE *logfile;

/* Log facility, 0 if logging to syslog is disabled */
int logfacility;

/* Current log level */
int loglevel;

/* Owner of the socket, -1 means no change */
uid_t socket_user = -1;

/* Owner of the socket, -1 means no change */
gid_t socket_group = -1;

/**
 * Prints the usage information for this program and, optionally, an error
 * message.  This function uses vfprintf.
 *	@param fd The file stream to print to
 *	@param msg The error message to print (printf style)
 *	@param ... Any args to msg (printf style)
 */
static void usage(FILE *fd, const char *msg, ...)
{
	/* List of args to msg */
	va_list ap;

	/* Put all args after msg into the list. */
	va_start(ap, msg);

	if (msg)
		vfprintf(fd, msg, ap);
	fprintf(fd, "\n");
	fprintf(fd, "cgrulesengd -- a daemon for the cgroups rules engine\n\n");
	fprintf(fd, "Usage : cgrulesengd [options]\n\n");
	fprintf(fd, "  options :\n");
	fprintf(fd, "    -q           | --quiet\t\t  quiet mode\n");
	fprintf(fd, "    -v           | --verbose\t\t  verbose mode\n");
	fprintf(fd, "    -f <path>    | --logfile=<path>\t  write log to file\n");
	fprintf(fd, "    -s[facility] | --syslog=[facility]\t  write log to syslog\n");
	fprintf(fd, "    -n           | --nodaemon\t\t  don't fork daemon\n");
	fprintf(fd, "    -d           | --debug\t\t  same as -v -v -n -f -\n");
	fprintf(fd, "    -Q           | --nolog\t\t  disable logging\n");
	fprintf(fd, "    -u <user>    | --socket-user=<user>   set");
	fprintf(fd, " " CGRULE_CGRED_SOCKET_PATH " socket user\n");
	fprintf(fd, "    -g <group>   | --socket-group=<group> set");
	fprintf(fd, " "	CGRULE_CGRED_SOCKET_PATH " socket group\n");
	fprintf(fd, "    -h           | --help\t\t  show this help\n\n");
	va_end(ap);
}

/**
 * Prints a formatted message (like vprintf()) to all log destinations.
 * Flushes the file stream's buffer so that the message is immediately
 * readable.
 *	@param level The log level (LOG_EMERG ... LOG_DEBUG)
 *	@param format The format for the message (vprintf style)
 *	@param ap Any args to format (vprintf style)
 */
void flog_write(int level, const char *format,  va_list ap)
{
	int copy = 0;
	va_list cap;

	/* Check the log level */
	if (level > loglevel)
		return;

	/* copy the argument list if needed - it can be processed only once */
	if (logfile && logfacility) {
		copy = 1;
		va_copy(cap, ap);
	}

	if (logfile) {
		/* Print the message to the given stream. */
		vfprintf(logfile, format, ap);

		/*
		 * Flush the stream's buffer, so the data
		 * is readable immediately.
		 */
		fflush(logfile);
	}

	if (logfacility) {
		if (copy) {
			vsyslog(LOG_MAKEPRI(logfacility, level), format, cap);
			va_end(cap);
		} else {
			vsyslog(LOG_MAKEPRI(logfacility, level), format, ap);
		}
	}
}

/**
 * Prints a formatted message (like printf()) to all log destinations.
 * Flushes the file stream's buffer so that the message is immediately
 * readable.
 *	@param level The log level (LOG_EMERG ... LOG_DEBUG)
 *	@param format The format for the message (printf style)
 *	@param ... Any args to format (printf style)
 */
void flog(int level, const char *format, ...)
{
	va_list ap;

	va_start(ap, format);
	flog_write(level, format, ap);
	va_end(ap);
}

/**
 * Libcgroup logging callback. It must translate libcgroup log levels
 * to cgrulesengd native (=syslog).
 */
void flog_cgroup(void *userdata, int cgroup_level, const char *format, va_list ap)
{
	int level = 0;

	switch (cgroup_level) {
	case CGROUP_LOG_ERROR:
		level = LOG_ERR;
		break;
	case CGROUP_LOG_WARNING:
		level = LOG_WARNING;
		break;
	case CGROUP_LOG_INFO:
		level = LOG_INFO;
		break;
	case CGROUP_LOG_DEBUG:
		level = LOG_DEBUG;
		break;
	}
	flog_write(level, format, ap);
}

struct parent_info {
	__u64 timestamp;
	pid_t pid;
};

struct array_parent_info {
	int index;
	int num_allocation;
	struct parent_info **parent_info;
};

struct array_parent_info array_pi;

static int cgre_store_parent_info(pid_t pid)
{
	struct parent_info *info;
	struct timespec tp;
	__u64 uptime_ns;

	if (clock_gettime(CLOCK_MONOTONIC, &tp) < 0) {
		flog(LOG_WARNING, "Failed to get time\n");
		return 1;
	}
	uptime_ns = ((__u64)tp.tv_sec * 1000 * 1000 * 1000) + tp.tv_nsec;

	if (array_pi.index >= array_pi.num_allocation) {
		int alloc = array_pi.num_allocation + NUM_PER_REALLOCATIOM;
		void *new_array = realloc(array_pi.parent_info, sizeof(info) * alloc);
		if (!new_array) {
			flog(LOG_WARNING, "Failed to allocate memory\n");
			return 1;
		}
		array_pi.parent_info = new_array;
		array_pi.num_allocation = alloc;
	}

	info = calloc(1, sizeof(struct parent_info));
	if (!info) {
		flog(LOG_WARNING, "Failed to allocate memory\n");
		return 1;
	}
	info->timestamp = uptime_ns;
	info->pid = pid;

	array_pi.parent_info[array_pi.index] = info;
	array_pi.index++;

	return 0;
}

static void cgre_remove_old_parent_info(__u64 key_timestamp)
{
	int i, j;

	for (i = 0; i < array_pi.index; i++) {
		if (key_timestamp < array_pi.parent_info[i]->timestamp)
			continue;

		free(array_pi.parent_info[i]);
		for (j = i; j < array_pi.index - 1; j++)
			array_pi.parent_info[j] = array_pi.parent_info[j + 1];
		array_pi.index--;
		i--;
	}
}

static int cgre_was_parent_changed_when_forking(const struct proc_event *ev)
{
	__u64 timestamp_parent;
	__u64 timestamp_child;
	pid_t parent_pid;
	int i;

	parent_pid = ev->event_data.fork.parent_pid;
	timestamp_child = ev->timestamp_ns;

	cgre_remove_old_parent_info(timestamp_child);

	for (i = 0; i < array_pi.index; i++) {
		if (parent_pid != array_pi.parent_info[i]->pid)
			continue;

		timestamp_parent = array_pi.parent_info[i]->timestamp;
		if (timestamp_child > timestamp_parent)
			continue;

		return 1;
	}

	return 0;
}

struct unchanged_pid {
	pid_t pid;
	int flags;
} unchanged_pid_t;

struct array_unchanged {
	int index;
	int num_allocation;
	struct unchanged_pid *proc;
};

struct array_unchanged array_unch;

static int cgre_store_unchanged_process(pid_t pid, int flags)
{
	int i;

	for (i = 0; i < array_unch.index; i++) {
		if (array_unch.proc[i].pid != pid)
			continue;
		/* pid is stored already. */
		return 0;
	}

	if (array_unch.index >= array_unch.num_allocation) {
		int alloc = array_unch.num_allocation + NUM_PER_REALLOCATIOM;
		void *new_array = realloc(array_unch.proc, sizeof(unchanged_pid_t) * alloc);
		if (!new_array) {
			flog(LOG_WARNING, "Failed to allocate memory\n");
			return 1;
		}
		array_unch.proc = new_array;
		array_unch.num_allocation = alloc;
	}
	array_unch.proc[array_unch.index].pid = pid;
	array_unch.proc[array_unch.index].flags = flags;
	array_unch.index++;

	flog(LOG_DEBUG, "Store the unchanged process (PID: %d, FLAGS: %d)\n", pid, flags);

	return 0;
}

static void cgre_remove_unchanged_process(pid_t pid)
{
	int i, j;

	for (i = 0; i < array_unch.index; i++) {
		if (array_unch.proc[i].pid != pid)
			continue;

		for (j = i; j < array_unch.index - 1; j++)
			memcpy(&array_unch.proc[j], &array_unch.proc[j + 1],
			       sizeof(struct unchanged_pid));
		array_unch.index--;
		flog(LOG_DEBUG, "Remove the unchanged process (PID: %d)\n", pid);
		return;
	}
}

static int cgre_is_unchanged_process(pid_t pid)
{
	int i;

	for (i = 0; i < array_unch.index; i++) {
		if (array_unch.proc[i].pid != pid)
			continue;
		return 1;
	}

	return 0;
}

static int cgre_is_unchanged_child(pid_t pid)
{
	int i;

	for (i = 0; i < array_unch.index; i++) {
		if (array_unch.proc[i].pid != pid)
			continue;
		if (array_unch.proc[i].flags & CGROUP_DAEMON_UNCHANGE_CHILDREN)
			return 1;
		break;
	}

	return 0;
}

/**
 * Process an event from the kernel, and determine the correct UID/GID/PID
 * to pass to libcgroup. Then, libcgroup will decide the cgroup to move
 * the PID to, if any.
 *	@param ev The event to process
 *	@param type The type of event to process (part of ev)
 *	@return 0 on success, > 0 on failure
 */
int cgre_process_event(const struct proc_event *ev, const int type)
{
	pid_t pid = 0, log_pid = 0;
	uid_t euid, log_uid = 0;
	gid_t egid, log_gid = 0;
	pid_t ppid, cpid;
	char *procname;

	int ret = 0;

	switch (type) {
	case PROC_EVENT_UID:
	case PROC_EVENT_GID:
		/*
		 * If the unchanged process, the daemon should not change
		 * the cgroup of the process.
		 */
		if (cgre_is_unchanged_process(ev->event_data.id.process_pid))
			return 0;
		pid = ev->event_data.id.process_pid;
		break;
	case PROC_EVENT_FORK:
		ppid = ev->event_data.fork.parent_pid;
		cpid = ev->event_data.fork.child_pid;
		if (cgre_is_unchanged_child(ppid)) {
			if (cgre_store_unchanged_process(cpid, CGROUP_DAEMON_UNCHANGE_CHILDREN))
				return 1;
		}

		/*
		 * If this process was forked while changing parent's
		 * cgroup, this process's cgroup also should be changed.
		 */
		if (!cgre_was_parent_changed_when_forking(ev))
			return 0;
		pid = ev->event_data.fork.child_pid;
		break;
	case PROC_EVENT_EXIT:
		cgre_remove_unchanged_process(ev->event_data.exit.process_pid);
		return 0;
	case PROC_EVENT_EXEC:
		/*
		 * If the unchanged process, the daemon should not change
		 * the cgroup of the process.
		 */
		if (cgre_is_unchanged_process(ev->event_data.exec.process_pid))
			return 0;
		pid = ev->event_data.exec.process_pid;
		break;
	default:
		break;
	}

	ret = cgroup_get_uid_gid_from_procfs(pid, &euid, &egid);
	if (ret == ECGROUPNOTEXIST)
		/*
		 * cgroup_get_uid_gid_from_procfs() returns ECGROUPNOTEXIST
		 * if a process finished and that is not a problem.
		 */
		return 0;
	else if (ret)
		return ret;

	ret = cgroup_get_procname_from_procfs(pid, &procname);
	if (ret == ECGROUPNOTEXIST)
		return 0;
	else if (ret)
		return ret;

	/*
	 * Now that we have the UID, the GID, and the PID, we can make a
	 * call to libcgroup to change the cgroup for this PID.
	 */
	log_pid = pid;
	switch (type) {
	case PROC_EVENT_UID:
		log_uid = ev->event_data.id.e.euid;
		log_gid = egid;
		euid = ev->event_data.id.e.euid;
		break;
	case PROC_EVENT_GID:
		log_uid = euid;
		log_gid = ev->event_data.id.e.egid;
		egid = ev->event_data.id.e.egid;
		break;
	case PROC_EVENT_FORK:
		log_uid = euid;
		log_gid = egid;
		break;
	case PROC_EVENT_EXEC:
		log_uid = euid;
		log_gid = egid;
		break;
	default:
		break;
	}

	ret = cgroup_change_cgroup_flags(euid, egid, procname, pid, CGFLAG_USECACHE);
	if (ret == ECGOTHER) {
		/*
		 * A process finished already but we may have missed
		 * changing it, make sure to apply to forked children.
		 */
		if (cgroup_get_last_errno() == ESRCH || cgroup_get_last_errno() == ENOENT)
			ret = cgre_store_parent_info(pid);
		else
			ret = 0;
	} else if (ret) {
		flog(LOG_WARNING, "Cgroup change for PID: %d, UID: %d, GID: %d, ", log_pid,
		     log_uid, log_gid);
		flog(LOG_WARNING, "PROCNAME: %s FAILED! (Error Code: %d)\n", procname, ret);
	} else {
		flog(LOG_INFO, "Cgroup change for PID: %d, UID: %d, GID: %d, PROCNAME: %s OK\n",
		     log_pid, log_uid, log_gid, procname);
		ret = cgre_store_parent_info(pid);
	}
	free(procname);

	return ret;
}

/**
 * Handle a netlink message.
 * In the event of PROC_EVENT_UID or PROC_EVENT_GID, we pass the event along
 * to cgre_process_event for further processing.  All other events are ignored.
 *	@param cn_hdr The netlink message
 *	@return 0 on success, > 0 on error
 */
static int cgre_handle_msg(struct cn_msg *cn_hdr)
{
	/* The event to consider */
	struct proc_event *ev;

	/* Return codes */
	int ret = 0;

	/* Get the event data.  We only care about two event types. */
	ev = (struct proc_event *)cn_hdr->data;
	switch (ev->what) {
	case PROC_EVENT_UID:
		flog(LOG_DEBUG, "UID Event: PID = %d, tGID = %d, rUID = %d, eUID = %d\n",
		     ev->event_data.id.process_pid, ev->event_data.id.process_tgid,
		     ev->event_data.id.r.ruid, ev->event_data.id.e.euid);
		ret = cgre_process_event(ev, PROC_EVENT_UID);
		break;
	case PROC_EVENT_GID:
		flog(LOG_DEBUG, "GID Event: PID = %d, tGID = %d, rGID = %d, eGID = %d\n",
		     ev->event_data.id.process_pid, ev->event_data.id.process_tgid,
		     ev->event_data.id.r.rgid, ev->event_data.id.e.egid);
		ret = cgre_process_event(ev, PROC_EVENT_GID);
		break;
	case PROC_EVENT_FORK:
		ret = cgre_process_event(ev, PROC_EVENT_FORK);
		break;
	case PROC_EVENT_EXIT:
		ret = cgre_process_event(ev, PROC_EVENT_EXIT);
		break;
	case PROC_EVENT_EXEC:
		flog(LOG_DEBUG, "EXEC Event: PID = %d, tGID = %d\n",
		     ev->event_data.exec.process_pid, ev->event_data.exec.process_tgid);
		ret = cgre_process_event(ev, PROC_EVENT_EXEC);
		break;
	default:
		break;
	}

	return ret;
}

static int cgre_receive_netlink_msg(int sk_nl)
{
	struct sockaddr_nl from_nla;
	socklen_t from_nla_len;
	struct cn_msg *cn_hdr;
	struct nlmsghdr *nlh;
	char buff[BUFF_SIZE];
	size_t recv_len;

	memset(buff, 0, sizeof(buff));
	from_nla_len = sizeof(from_nla);
	recv_len = recvfrom(sk_nl, buff, sizeof(buff), 0, (struct sockaddr *)&from_nla,
			    &from_nla_len);
	if (recv_len == ENOBUFS) {
		flog(LOG_ERR, "ERROR: NETLINK BUFFER FULL, MESSAGE DROPPED!\n");
		return 0;
	}

	if (recv_len < 1)
		return 0;

	if (from_nla_len != sizeof(from_nla)) {
		flog(LOG_ERR, "Bad address size reading netlink socket\n");
		return 0;
	}

	if (from_nla.nl_groups != CN_IDX_PROC || from_nla.nl_pid != 0)
		return 0;

	nlh = (struct nlmsghdr *)buff;
	while (NLMSG_OK(nlh, recv_len)) {
		cn_hdr = NLMSG_DATA(nlh);

		if (nlh->nlmsg_type == NLMSG_NOOP) {
			nlh = NLMSG_NEXT(nlh, recv_len);
			continue;
		}
		if ((nlh->nlmsg_type == NLMSG_ERROR) || (nlh->nlmsg_type == NLMSG_OVERRUN))
			break;
		if (cgre_handle_msg(cn_hdr) < 0)
			return 1;
		if (nlh->nlmsg_type == NLMSG_DONE)
			break;
		nlh = NLMSG_NEXT(nlh, recv_len);
	}

	return 0;
}

static void cgre_receive_unix_domain_msg(int sk_unix)
{
	struct sockaddr_un caddr;
	char path[FILENAME_MAX];
	struct stat buff_stat;
	socklen_t caddr_len;
	size_t ret_len;
	int fd_client;
	int flags;
	pid_t pid;

	caddr_len = sizeof(caddr);
	fd_client = accept(sk_unix, (struct sockaddr *)&caddr, &caddr_len);
	if (fd_client < 0) {
		flog(LOG_WARNING, "Warning: 'accept' command error: %s\n", strerror(errno));
		return;
	}

	ret_len = read(fd_client, &pid, sizeof(pid));
	if (ret_len != sizeof(pid)) {
		flog(LOG_WARNING, "Warning: 'read' command error: %s\n", strerror(errno));
		goto close;
	}

	sprintf(path, "/proc/%d", pid);
	if (stat(path, &buff_stat)) {
		flog(LOG_WARNING, "Warning: there is no such process (PID: %d)\n", pid);
		goto close;
	}

	ret_len = read(fd_client, &flags, sizeof(flags));
	if (ret_len != sizeof(flags)) {
		flog(LOG_WARNING, "Warning: error reading daemon socket: %s\n", strerror(errno));
		goto close;
	}

	if (flags == CGROUP_DAEMON_CANCEL_UNCHANGE_PROCESS)
		cgre_remove_unchanged_process(pid);
	else if (cgre_store_unchanged_process(pid, flags))
		goto close;

	if (write(fd_client, CGRULE_SUCCESS_STORE_PID, sizeof(CGRULE_SUCCESS_STORE_PID)) < 0) {
		flog(LOG_WARNING, "Warning: cannot write to daemon socket: %s\n", strerror(errno));
		goto close;
	}

close:
	close(fd_client);
}

static int cgre_create_netlink_socket_process_msg(void)
{
	int sk_nl = 0, sk_unix = 0, sk_max;
	enum proc_cn_mcast_op *mcop_msg;
	struct sockaddr_nl my_nla;
	struct sockaddr_un saddr;
	struct nlmsghdr *nl_hdr;
	struct cn_msg *cn_hdr;
	char buff[BUFF_SIZE];
	fd_set fds, readfds;
	sigset_t sigset;
	int rc = -1;

	/*
	 * Create an endpoint for communication. Use the kernel user interface
	 * device (PF_NETLINK) which is a datagram oriented service (SOCK_DGRAM).
	 * The protocol used is the connector protocol (NETLINK_CONNECTOR)
	 */
	sk_nl = socket(PF_NETLINK, SOCK_DGRAM, NETLINK_CONNECTOR);
	if (sk_nl == -1) {
		flog(LOG_ERR, "Error: error opening netlink socket: %s\n", strerror(errno));
		return rc;
	}

	my_nla.nl_family = AF_NETLINK;
	my_nla.nl_groups = CN_IDX_PROC;
	my_nla.nl_pid = getpid();
	my_nla.nl_pad = 0;

	if (bind(sk_nl, (struct sockaddr *)&my_nla, sizeof(my_nla)) < 0) {
		flog(LOG_ERR, "Error: error binding netlink socket: %s\n", strerror(errno));
		goto close_and_exit;
	}

	nl_hdr = (struct nlmsghdr *)buff;
	cn_hdr = (struct cn_msg *)NLMSG_DATA(nl_hdr);
	mcop_msg = (enum proc_cn_mcast_op *)&cn_hdr->data[0];
	flog(LOG_DEBUG, "Sending proc connector: PROC_CN_MCAST_LISTEN...\n");
	memset(buff, 0, sizeof(buff));
	*mcop_msg = PROC_CN_MCAST_LISTEN;

	/* fill the netlink header */
	nl_hdr->nlmsg_len = SEND_MESSAGE_LEN;
	nl_hdr->nlmsg_type = NLMSG_DONE;
	nl_hdr->nlmsg_flags = 0;
	nl_hdr->nlmsg_seq = 0;
	nl_hdr->nlmsg_pid = getpid();

	/* fill the connector header */
	cn_hdr->id.idx = CN_IDX_PROC;
	cn_hdr->id.val = CN_VAL_PROC;
	cn_hdr->seq = 0;
	cn_hdr->ack = 0;
	cn_hdr->len = sizeof(enum proc_cn_mcast_op);
	flog(LOG_DEBUG, "Sending netlink message len=%d, cn_msg len=%d\n", nl_hdr->nlmsg_len,
	     (int) sizeof(struct cn_msg));

	if (send(sk_nl, nl_hdr, nl_hdr->nlmsg_len, 0) != nl_hdr->nlmsg_len) {
		flog(LOG_ERR, "Error: failed to send netlink message (mcast ctl op): %s\n",
		     strerror(errno));
		goto close_and_exit;
	}
	flog(LOG_DEBUG, "Message sent\n");

	/* Setup Unix domain socket. */
	sk_unix = socket(PF_UNIX, SOCK_STREAM, 0);
	if (sk_unix < 0) {
		flog(LOG_ERR, "Error creating UNIX socket: %s\n", strerror(errno));
		goto close_and_exit;
	}

	memset(&saddr, 0, sizeof(saddr));
	saddr.sun_family = AF_UNIX;
	strcpy(saddr.sun_path, CGRULE_CGRED_SOCKET_PATH);
	unlink(CGRULE_CGRED_SOCKET_PATH);

	if (bind(sk_unix, (struct sockaddr *)&saddr,
	    sizeof(saddr.sun_family) + strlen(CGRULE_CGRED_SOCKET_PATH)) < 0) {
		flog(LOG_ERR, "Error binding UNIX socket %s: %s\n", CGRULE_CGRED_SOCKET_PATH,
		     strerror(errno));
		goto close_and_exit;
	}

	if (listen(sk_unix, 1) < 0) {
		flog(LOG_ERR, "Error listening on UNIX socket %s: %s\n", CGRULE_CGRED_SOCKET_PATH,
		     strerror(errno));
		goto close_and_exit;
	}

	/* change the owner */
	if (chown(CGRULE_CGRED_SOCKET_PATH, socket_user, socket_group) < 0) {
		flog(LOG_ERR, "Error changing %s socket owner: %s\n", CGRULE_CGRED_SOCKET_PATH,
		     strerror(errno));
		goto close_and_exit;
	}

	flog(LOG_DEBUG, "Socket %s owner successfully set to %d:%d\n", CGRULE_CGRED_SOCKET_PATH,
	     (int) socket_user, (int) socket_group);

	if (chmod(CGRULE_CGRED_SOCKET_PATH, 0660) < 0) {
		flog(LOG_ERR, "Error changing %s socket permissions: %s\n",
		     CGRULE_CGRED_SOCKET_PATH, strerror(errno));
		goto close_and_exit;
	}

	FD_ZERO(&readfds);
	FD_SET(sk_nl, &readfds);
	FD_SET(sk_unix, &readfds);
	if (sk_nl < sk_unix)
		sk_max = sk_unix;
	else
		sk_max = sk_nl;

	sigemptyset(&sigset);
	sigaddset(&sigset, SIGUSR2);
	for (;;) {
		/*
		 * For avoiding the deadlock and "Interrupted system call"
		 * error, restrict the effective range of SIGUSR2 signal.
		 */
		sigprocmask(SIG_UNBLOCK, &sigset, NULL);
		sigprocmask(SIG_BLOCK, &sigset, NULL);

		memcpy(&fds, &readfds, sizeof(fd_set));
		if (select(sk_max + 1, &fds, NULL, NULL, NULL) < 0) {
			flog(LOG_ERR, "Selecting error: %s\n", strerror(errno));
			goto close_and_exit;
		}

		if (FD_ISSET(sk_nl, &fds)) {
			if (cgre_receive_netlink_msg(sk_nl))
				break;
		}

		if (FD_ISSET(sk_unix, &fds))
			cgre_receive_unix_domain_msg(sk_unix);
	}

close_and_exit:
	if (sk_nl >= 0)
		close(sk_nl);
	if (sk_unix >= 0)
		close(sk_unix);

	return rc;
}

/**
 * Start logging. Opens syslog and/or log file and sets log level.
 *	@param logp Path of the log file, NULL if no log file was specified
 *	@param logf Syslog facility, NULL if no facility was specified
 *	@param logv Log verbosity, 1 is the default, 0 = no logging, 4 = everything
 */
static void cgre_start_log(const char *logp, int logf, int logv)
{
	/* Current system time */
	time_t tm;

	/* Log levels */
	int loglevels[] = {
		LOG_EMERG,		/* -q */
		LOG_ERR,		/* default */
		LOG_WARNING,		/* -v */
		LOG_INFO,		/* -vv */
		LOG_DEBUG		/* -vvv */
	};

	/* Set default logging destination if nothing was specified */
	if (!logp && !logf)
		logf = LOG_DAEMON;

	/* Open log file */
	if (logp) {
		if (strcmp("-", logp) == 0) {
			logfile = stdout;
		} else {
			logfile = fopen(logp, "a");
			if (!logfile) {
				fprintf(stderr, "Failed to open log file %s,", logp);
				fprintf(stderr, " error: %s. Continuing anyway.\n",
					strerror(errno));
				logfile = stdout;
			}
		}
	} else
		logfile = NULL;

	/* Open syslog */
	if (logf) {
		openlog("CGRE", LOG_CONS | LOG_PID, logf);
		logfacility = logf;
	} else
		logfacility = 0;

	/* Set the log level */
	if (logv < 0)
		logv = 0;
	if (logv >= sizeof(loglevels)/sizeof(int))
		logv = sizeof(loglevels)/sizeof(int)-1;

	loglevel = loglevels[logv];
	cgroup_set_logger(flog_cgroup, CGROUP_LOG_DEBUG, NULL);

	flog(LOG_DEBUG, "CGroup Rules Engine Daemon log started\n");

	tm = time(0);
	flog(LOG_DEBUG, "Current time: %s\n", ctime(&tm));
	flog(LOG_DEBUG, "Opened log file: %s, log facility: %d,log level: %d\n", logp, logfacility,
	     loglevel);
}

/**
 * Turns this program into a daemon.  In doing so, we fork() and kill the
 * parent process.  Note too that stdout, stdin, and stderr are closed in
 * daemon mode, and a file descriptor for a log file is opened.
 *	@param logp Path of the log file, NULL if no log file was specified
 *	@param logf Syslog facility, 0 if no facility was specified
 *	@param daemon False to turn off daemon mode (no fork, leave FDs open)
 *	@param logv Log verbosity, 1 is the default, 0 = no logging, 5 = everything
 *	@return 0 on success, > 0 on error
 */
int cgre_start_daemon(const char *logp, const int logf, const unsigned char daemon, const int logv)
{
	/* PID returned from the fork() */
	pid_t pid;

	/* Fork and die. */
	if (daemon) {
		pid = fork();
		if (pid < 0) {
			openlog("CGRE", LOG_CONS, LOG_DAEMON|LOG_WARNING);
			syslog(LOG_DAEMON|LOG_WARNING, "Failed to fork, error: %s",
			       strerror(errno));
			closelog();

			fprintf(stderr, "Failed to fork(), %s\n", strerror(errno));
			return 1;
		} else if (pid > 0) {
			exit(EXIT_SUCCESS);
		}
	} else {
		flog(LOG_DEBUG, "Not using daemon mode\n");
		pid = getpid();
	}

	cgre_start_log(logp, logf, logv);

	if (!daemon) {
		/* We can skip the rest, since we're not becoming a daemon. */
		flog(LOG_INFO, "Proceeding with PID %d\n", getpid());
		return 0;
	}

	/* Get a new SID for the child. */
	if (setsid() < 0) {
		flog(LOG_ERR, "Failed to get a new SID, error: %s\n", strerror(errno));
		return 2;
	}

	/* Change to the root directory. */
	if (chdir("/") < 0) {
		flog(LOG_ERR, "Failed to chdir to /, error: %s\n", strerror(errno));
		return 3;
	}

	/* Close standard file descriptors. */
	close(STDIN_FILENO);
	if (logfile != stdout)
		close(STDOUT_FILENO);
	close(STDERR_FILENO);

	/* If we make it this far, we're a real daemon! Or we chose not to.  */
	flog(LOG_INFO, "Proceeding with PID %d\n", getpid());

	return 0;
}

/**
 * Catch the SIGUSR2 signal and reload the rules configuration.
 * This function makes use of the logfile and flog() to print the new rules.
 *	@param signum The signal that we caught (always SIGUSR2)
 */
void cgre_flash_rules(int signum)
{
	/* Current time */
	time_t tm = time(0);

	int fileindex;

	flog(LOG_INFO, "Reloading rules configuration\n");
	flog(LOG_DEBUG, "Current time: %s\n", ctime(&tm));

	/* Ask libcgroup to reload the rules table. */
	cgroup_reload_cached_rules();

	/* Print the results of the new table to our log file. */
	if (logfile && loglevel >= LOG_INFO) {
		cgroup_print_rules_config(logfile);
		fprintf(logfile, "\n");
	}

	/* Ask libcgroup to reload the template rules table. */
	cgroup_load_templates_cache_from_files(&fileindex);
}

/**
 * Catch the SIGUSR1 signal and reload the rules configuration.
 * This function makes use of the logfile and flog() to print the new rules.
 *	@param signum The signal that we caught (always SIGUSR1)
 */
void cgre_flash_templates(int signum)
{
	/* Current time */
	time_t tm = time(0);

	int fileindex;

	flog(LOG_INFO, "Reloading templates configuration.\n");
	flog(LOG_DEBUG, "Current time: %s\n", ctime(&tm));

	/* Ask libcgroup to reload the templates table. */
	cgroup_load_templates_cache_from_files(&fileindex);
}

/**
 * Catch the SIGTERM and SIGINT signals so that we can exit gracefully.
 * Before exiting, this function makes use of the logfile and flog().
 *	@param signum The signal that we caught (SIGTERM, SIGINT)
 */
void cgre_catch_term(int signum)
{
	/* Current time */
	time_t tm = time(0);

	flog(LOG_INFO, "Stopped CGroup Rules Engine Daemon at %s\n", ctime(&tm));

	/* Close the log file, if we opened one */
	if (logfile && logfile != stdout)
		fclose(logfile);

	/* Close syslog */
	if (logfacility)
		closelog();

	exit(EXIT_SUCCESS);
}

/**
 * Parse the syslog facility as received on command line.
 *	@param arg Command line argument with the syslog facility
 *	@return the syslog facility (e.g. LOG_DAEMON) or 0 on error
 */
static int cgre_parse_syslog_facility(const char *arg)
{
	if (arg == NULL)
		return 0;

	if (strlen(arg) > 1)
		return 0;

	switch (arg[0]) {
	case '0':
		return LOG_LOCAL0;
	case '1':
		return LOG_LOCAL1;
	case '2':
		return LOG_LOCAL2;
	case '3':
		return LOG_LOCAL3;
	case '4':
		return LOG_LOCAL4;
	case '5':
		return LOG_LOCAL5;
	case '6':
		return LOG_LOCAL6;
	case '7':
		return LOG_LOCAL7;
	case 'D':
		return LOG_DAEMON;
	default:
		return 0;
	}
}

int main(int argc, char *argv[])
{
	/* Patch to the log file */
	const char *logp = NULL;

	/* Syslog facility */
	int facility = 0;

	/* Verbose level */
	int verbosity = 1;

	/* For catching signals */
	struct sigaction sa;

	/* Should we daemonize? */
	unsigned char daemon = 1;

	/* Return codes */
	int ret = 0;

	struct passwd *pw;
	struct group *gr;

	/* Command line arguments */
	const char *short_options = "hvqf:s::ndQu:g:";
	struct option long_options[] = {
		{"help",	       no_argument, NULL, 'h'},
		{"verbose",	       no_argument, NULL, 'v'},
		{"quiet",	       no_argument, NULL, 'q'},
		{"logfile",	 required_argument, NULL, 'f'},
		{"syslog",	 optional_argument, NULL, 's'},
		{"nodaemon",	       no_argument, NULL, 'n'},
		{"debug",	       no_argument, NULL, 'd'},
		{"nolog",	       no_argument, NULL, 'Q'},
		{"socket-user",  required_argument, NULL, 'u'},
		{"socket-group", required_argument, NULL, 'g'},
		{NULL, 0, NULL, 0}
	};

	int fileindex;

	/* Make sure the user is root. */
	if (getuid() != 0) {
		fprintf(stderr, "Error: Only root can start/stop the control");
		fprintf(stderr,	" group rules engine daemon\n");
		ret = 1;
		goto finished;
	}

	/*
	 * Check environment variable CGROUP_LOGLEVEL. If it's set to
	 * DEBUG, set appropriate verbosity level.
	 */
	char *level_str = getenv("CGROUP_LOGLEVEL");

	if (level_str != NULL) {
		if (cgroup_parse_log_level_str(level_str) == CGROUP_LOG_DEBUG) {
			verbosity = 4;
			logp = "-";
		}
	}

	while (1) {
		int c;

		c = getopt_long(argc, argv, short_options, long_options, NULL);
		if (c == -1)
			break;

		switch (c) {
		case 'h':   /* --help */
			usage(stdout, "Help:\n");
			ret = 0;
			goto finished;

		case 'v':   /* --verbose */
			verbosity++;
			break;

		case 'q':   /* --quiet */
			verbosity--;
			break;

		case 'Q':   /* --nolog */
			verbosity = 0;
			break;

		case 'f':   /* --logfile=<filename> */
			logp = optarg;
			break;

		case 's':   /* --syslog=[facility] */
			if (optarg) {
				facility = cgre_parse_syslog_facility(optarg);
				if (facility == 0) {
					fprintf(stderr, "Unknown syslog facility: %s\n", optarg);
					ret = 2;
					goto finished;
				}
			} else {
				facility = LOG_DAEMON;
			}
			break;

		case 'n':   /* --no-fork */
			daemon = 0;
			break;

		case 'd':   /* --debug */
			/* same as -vvn */
			daemon = 0;
			verbosity = 4;
			logp = "-";
			break;
		case 'u': /* --socket-user */
			pw = getpwnam(optarg);
			if (pw == NULL) {
				usage(stderr, "Cannot find user %s", optarg);
				ret = 3;
				goto finished;
			}
			socket_user = pw->pw_uid;
			flog(LOG_DEBUG, "Using socket user %s id %d\n",	optarg, (int)socket_user);
			break;
		case 'g': /* --socket-group */
			gr = getgrnam(optarg);
			if (gr == NULL) {
				usage(stderr, "Cannot find group %s", optarg);
				ret = 3;
				goto finished;
			}
			socket_group = gr->gr_gid;
			flog(LOG_DEBUG, "Using socket group %s id %d\n", optarg,
			     (int)socket_group);
			break;
		default:
			usage(stderr, "");
			ret = 2;
			goto finished;
		}
	}

	/* Initialize libcgroup. */
	ret = cgroup_init();
	if (ret != 0) {
		fprintf(stderr, "Error: libcgroup initialization failed, %s\n",
			cgroup_strerror(ret));
		goto finished;
	}

	/* Ask libcgroup to load the configuration rules. */
	ret = cgroup_string_list_init(&template_files, CGCONFIG_CONF_FILES_LIST_MINIMUM_SIZE);
	if (ret) {
		fprintf(stderr, "%s: cannot init file list, out of memory?\n", argv[0]);
		goto finished_without_temp_files;
	}
	/* first add CGCONFIG_CONF_FILE into file list */
	ret = cgroup_string_list_add_item(&template_files, CGCONFIG_CONF_FILE);
	if (ret) {
		fprintf(stderr,	"%s: cannot add file to list, out of memory?\n", argv[0]);
		goto finished;
	}

	/* then read CGCONFIG_CONF_DIR directory for additional config files */
	cgroup_string_list_add_directory(&template_files, CGCONFIG_CONF_DIR, argv[0]);

	ret = cgroup_init_rules_cache();
	if (ret != 0) {
		fprintf(stderr, "Error: libcgroup failed to initialize rules");
		fprintf(stderr, "cache from %s. %s\n", CGRULES_CONF_FILE, cgroup_strerror(ret));
		goto finished;
	}

	/* ask libcgroup to load template rules as well */
	cgroup_templates_cache_set_source_files(&template_files);
	ret = cgroup_load_templates_cache_from_files(&fileindex);
	if (ret != 0) {
		if (fileindex < 0)
			fprintf(stderr, "Error: Template source files have not been set\n");
		else
			fprintf(stderr, "Error: Failed to initialize template rules from %s.%s\n",
				template_files.items[fileindex], cgroup_strerror(-ret));
		goto finished;
	}

	/* Now, start the daemon. */
	ret = cgre_start_daemon(logp, facility, daemon, verbosity);
	if (ret < 0) {
		fprintf(stderr, "Error: Failed to launch the daemon, %s\n", cgroup_strerror(ret));
		goto finished;
	}

	/*
	 * Set up the signal handler to reload the cached rules upon
	 * reception of a SIGUSR2 signal.
	 */
	memset(&sa, 0, sizeof(sa));
	sa.sa_handler = &cgre_flash_rules;
	sigemptyset(&sa.sa_mask);
	ret = sigaction(SIGUSR2, &sa, NULL);
	if (ret) {
		flog(LOG_ERR, "Failed to set up signal handler for SIGUSR2. Error: %s\n",
		     strerror(errno));
		goto finished;
	}

	/*
	 * Set up the signal handler to reload templates cache upon
	 * reception of a SIGUSR1 signal.
	 */
	sa.sa_handler = &cgre_flash_templates;
	ret = sigaction(SIGUSR1, &sa, NULL);
	if (ret) {
		flog(LOG_ERR, "Failed to set up signal handler for SIGUSR1. Error: %s\n",
		     strerror(errno));
		goto finished;
	}

	/*
	 * Set up the signal handler to catch SIGINT and SIGTERM so that
	 * we can exit gracefully
	 */
	sa.sa_handler = &cgre_catch_term;
	ret = sigaction(SIGINT, &sa, NULL);
	ret |= sigaction(SIGTERM, &sa, NULL);
	if (ret) {
		flog(LOG_ERR, "Failed to set up the signal handler. Error: %s\n", strerror(errno));
		goto finished;
	}

	/* Print the configuration to the log file, or stdout. */
	if (logfile && loglevel >= LOG_INFO)
		cgroup_print_rules_config(logfile);

	/* Scan for running applications with rules */
	ret = cgroup_change_all_cgroups();
	if (ret)
		flog(LOG_WARNING, "Failed to initialize running tasks.\n");

	flog(LOG_INFO, "Started the CGroup Rules Engine Daemon.\n");

	/* We loop endlesly in this function, unless we encounter an error. */
	ret =  cgre_create_netlink_socket_process_msg();

finished:
	cgroup_string_list_free(&template_files);

finished_without_temp_files:
	if (logfile && logfile != stdout)
		fclose(logfile);

	return ret;
}
