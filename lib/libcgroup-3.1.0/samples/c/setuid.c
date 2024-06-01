// SPDX-License-Identifier: LGPL-2.1-only
/*
 * Copyright Red Hat Inc. 2008
 *
 * Author:      Steve Olivieri <sjo@redhat.com>
 */

#include <stdlib.h>
#include <unistd.h>
#include <string.h>
#include <errno.h>
#include <stdio.h>
#include <pwd.h>
#include <grp.h>

#include <sys/types.h>

/*
 * This is just a simple program for changing a UID or a GID.  Comment out
 * whichever block you don't want to use.
 */
int main(int argc, char *argv[])
{
	/* User data */
	struct passwd *pwd;

	/* UID of user */
	uid_t uid;

	/* Return codes */
	int ret = 0;

	if (argc < 2) {
		printf("Usage: %s <uid_value>\n", argv[0]);
		goto finished;
	}

	pwd = getpwnam(argv[1]);
	if (!pwd) {
		fprintf(stderr, "getpwnam() failed: %s\n",
			strerror(errno));
		ret = -errno;
		goto finished;
	}

	uid = pwd->pw_uid;
	fprintf(stdout, "Setting UID to %s (%d).\n", pwd->pw_name, uid);
	ret = setuid(uid);
	if (ret != 0) {
		fprintf(stderr, "Call to setuid() failed with error: %s\n",
			strerror(errno));
		ret = -errno;
		goto finished;
	}

//	while(1) {
//		grp = getgrnam("root");
//		gid = grp->gr_gid;
//		fprintf(stdout, "Setting GID to %s (%d).\n",
//				grp->gr_name, gid);
//		if ((ret = setgid(gid))) {
//			fprintf(stderr, "Call to setgid() failed with error:"
//					" %s\n", strerror(errno));
//			ret = -errno;
//			goto finished;
//		}
//	}

	while (1)
		usleep(3000000);

finished:
	return ret;
}
