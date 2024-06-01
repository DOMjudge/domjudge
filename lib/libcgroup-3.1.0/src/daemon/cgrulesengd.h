/* SPDX-License-Identifier: LGPL-2.1-only */
/**
 * Copyright Red Hat Inc. 2008
 *
 * Author:      Steve Olivieri <sjo@redhat.com>
 */

#ifndef _CGRULESENGD_H
#define _CGRULESENGD_H

#include <features.h>

#ifdef __cplusplus
extern "C" {
#endif

#include "config.h"
#include "libcgroup.h"

#include <linux/connector.h>
#include <linux/cn_proc.h>

#ifndef _GNU_SOURCE
#define _GNU_SOURCE
#endif

#ifndef __USE_GNU
#define __USE_GNU
#endif

/* The following ten macros are all for the Netlink code. */
#define SEND_MESSAGE_LEN (NLMSG_LENGTH(sizeof(struct cn_msg) + sizeof(enum proc_cn_mcast_op)))
#define RECV_MESSAGE_LEN (NLMSG_LENGTH(sizeof(struct cn_msg) + sizeof(struct proc_event)))

#define SEND_MESSAGE_SIZE (NLMSG_SPACE(SEND_MESSAGE_LEN))
#define RECV_MESSAGE_SIZE (NLMSG_SPACE(RECV_MESSAGE_LEN))

#define BUFF_SIZE	(max(max(SEND_MESSAGE_SIZE, RECV_MESSAGE_SIZE), 1024))
#define MIN_RECV_SIZE	(min(SEND_MESSAGE_SIZE, RECV_MESSAGE_SIZE))

#define PROC_CN_MCAST_LISTEN (1)
#define PROC_CN_MCAST_IGNORE (2)

/**
 * Prints the usage information for this program and, optionally,
 * an error message. This function uses vfprintf.
 *	@param fd The file stream to print to
 *	@param msg The error message to print (printf style)
 *	@param ... Any args to msg (printf style)
 */
void cgre_usage(FILE *fd, const char *msg, ...);

/**
 * Prints a formatted message (like printf()) to all log destinations.
 * Flushes the file stream's buffer so that the message is immediately
 * readable.
 *	@param level The log level (LOG_EMERG ... LOG_DEBUG)
 *	@param format The format for the message (printf style)
 *	@param ... Any args to format (printf style)
 */
void flog(int level, const char *msg, ...);

/**
 * Process an event from the kernel, and determine the correct UID/GID/PID
 * to pass to libcgroup. Then, libcgroup will decide the cgroup to move
 * the PID to, if any.
 *	@param ev The event to process
 *	@param type The type of event to process (part of ev)
 *	@return 0 on success, > 0 on failure
 */
int cgre_process_event(const struct proc_event *ev, const int type);

/**
 * Handle a netlink message.
 * In the event of PROC_EVENT_UID or PROC_EVENT_GID, we pass the event
 * along to cgre_process_event for further processing.  All other events
 * are ignored.
 *	@param cn_hdr The netlink message
 *	@return 0 on success, > 0 on error
 */
int cgre_handle_message(struct cn_msg *cn_hdr);

/**
 * Turns this program into a daemon.  In doing so, we fork() and kill the
 * parent process.  Note too that stdout, stdin, and stderr are closed in
 * daemon mode, and a file descriptor for a log file is opened.
 *	@param logp Path of the log file, NULL if no log file was specified
 *	@param logf Syslog facility, NULL if no facility was specified
 *	@param daemon False to turn off daemon mode (no fork, leave FDs open)
 *	@param logv Log verbosity:
 *		2 is the default, 0 = no logging, 5 = everything
 *	@return 0 on success, > 0 on error
 */
int cgre_start_daemon(const char *logp, const int logf, const unsigned char daemon,
		      const int logv);

/**
 * Catch the SIGUSR2 signal and reload the rules configuration.
 * This function makes use of the logfile and flog() to print the new rules.
 *	@param signum The signal that we caught (always SIGUSR2)
 */
void cgre_flash_rules(int signum);

/**
 * Catch the SIGUSR1 signal and reload the rules configuration.
 * This function makes use of the logfile and flog() to print the new rules.
 *	@param signum The signal that we caught (always SIGUSR1)
 */
void cgre_flash_templates(int signum);

/**
 * Catch the SIGTERM and SIGINT signal so that we can exit gracefully.
 * Before exiting, this function makes use of the logfile and flog().
 *	@param signum The signal that we caught (SIGTERM, SIGINT)
 */
void cgre_catch_term(int signum);

#ifdef __cplusplus
} /* extern "C" */
#endif

#endif /* _CGRULESENGD_H */
