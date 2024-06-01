// SPDX-License-Identifier: LGPL-2.1-only
/*
 * Copyright RedHat Inc. 2008
 *
 * Author:      Vivek Goyal <vgoyal@redhat.com>
 *
 * Derived from pam_limits.c. Original Copyright notice follows.
 *
 * Copyright (c) Cristian Gafton, 1996-1997, <gafton@redhat.com>
 *                                              All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, and the entire permission notice in its entirety,
 *    including the disclaimer of warranties.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote
 *    products derived from this software without specific prior
 *    written permission.
 *
 * ALTERNATIVELY, this product may be distributed under the terms of
 * the GNU Public License, in which case the provisions of the GPL are
 * required INSTEAD OF the above restrictions.  (This clause is
 * necessary due to a potential bad interaction between the GPL and
 * the restrictions contained in a BSD-style copyright.)
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
 * OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED.  IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT,
 * INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 * STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 * OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * End of original copyright notice.
 */

#include <libcgroup.h>

#include <syslog.h>
#include <unistd.h>
#include <string.h>
#include <stdlib.h>
#include <stdio.h>
#include <ctype.h>
#include <errno.h>
#include <pwd.h>

/*
 * Module defines
 */
#define PAM_SM_SESSION

#include <security/pam_modules.h>
#include <security/_pam_macros.h>
#include <security/pam_modutil.h>
#include <security/pam_ext.h>

/* argument parsing */
#define PAM_DEBUG_ARG       0x0001

static int _pam_parse(const pam_handle_t *pamh, int argc, const char **argv)
{
	int ctrl = 0;

	/* step through arguments */
	for (ctrl = 0; argc-- > 0; ++argv) {
		if (!strcmp(*argv, "debug"))
			ctrl |= PAM_DEBUG_ARG;
		else
			pam_syslog(pamh, LOG_ERR, "unknown option: %s", *argv);
	}

	return ctrl;
}

/* now the session stuff */
PAM_EXTERN int pam_sm_open_session(pam_handle_t *pamh, int flags, int argc, const char **argv)
{
	struct passwd *pwd;
	char *user_name;
	int ctrl, ret;
	pid_t pid;

	D(("called."));

	ctrl = _pam_parse(pamh, argc, argv);

	ret = pam_get_item(pamh, PAM_USER, (void *) &user_name);
	if (user_name == NULL || ret != PAM_SUCCESS)  {
		pam_syslog(pamh, LOG_ERR, "open_session - error recovering username");
		return PAM_SESSION_ERR;
	}

	pwd = pam_modutil_getpwnam(pamh, user_name);
	if (!pwd) {
		if (ctrl & PAM_DEBUG_ARG)
			pam_syslog(pamh, LOG_ERR, "open_session username '%s' does not exist",
				   user_name);
		return PAM_SESSION_ERR;
	}

	D(("user name is %s", user_name));

	/* Initialize libcg */
	ret = cgroup_init();
	if (ret) {
		if (ctrl & PAM_DEBUG_ARG)
			pam_syslog(pamh, LOG_ERR, "libcgroup initialization failed");
		return PAM_SESSION_ERR;
	}

	D(("Initialized libcgroup successfuly."));

	/* Determine the pid of the task */
	pid = getpid();

	/*
	 * Note: We are using default gid here. Is there a way to
	 * determine under what egid service will be provided?
	 */
	ret = cgroup_change_cgroup_uid_gid_flags(pwd->pw_uid, pwd->pw_gid, pid, CGFLAG_USECACHE);
	if (ret) {
		if (ctrl & PAM_DEBUG_ARG) {
			pam_syslog(pamh, LOG_ERR, "Change of cgroup for process with username");
			pam_syslog(pamh, LOG_ERR, "%s failed.\n", user_name);
		}
		return PAM_SESSION_ERR;
	}

	if (ctrl & PAM_DEBUG_ARG) {
		pam_syslog(pamh, LOG_DEBUG, "Changed cgroup for process %d with username %s.\n",
			   pid, user_name);
	}

	return PAM_SUCCESS;
}

PAM_EXTERN int pam_sm_close_session(pam_handle_t *pamh, int flags, int argc, const char **argv)
{
	D(("called pam_cgroup close session"));

	/* nothing to do yet */
	return PAM_SUCCESS;
}
