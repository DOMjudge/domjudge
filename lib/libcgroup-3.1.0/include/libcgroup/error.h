/* SPDX-License-Identifier: LGPL-2.1-only */
#ifndef _LIBCGROUP_ERROR_H
#define _LIBCGROUP_ERROR_H

#ifndef _LIBCGROUP_H_INSIDE
#error "Only <libcgroup.h> should be included directly."
#endif

#ifndef SWIG
#include <features.h>
#endif

#ifdef __cplusplus
extern "C" {
#endif

/**
 * @defgroup group_errors 6. Error handling
 * @{
 *
 * @name Error handling
 * @{
 * Unless states otherwise in documentation of a function, all functions
 * return @c int, which is zero (0) when the function succeeds, and positive
 * number if the function fails.
 *
 * The returned integer is one of the ECG* values described below. Value
 * #ECGOTHER means that the error was caused by underlying OS and the real
 * cause can be found by calling cgroup_get_last_errno().
 */

enum {
	ECGROUPNOTCOMPILED = 50000,
	ECGROUPNOTMOUNTED,		/* 50001 */
	ECGROUPNOTEXIST,		/* 50002 */
	ECGROUPNOTCREATED,		/* 50003 */
	ECGROUPSUBSYSNOTMOUNTED,	/* 50004 */
	ECGROUPNOTOWNER,		/* 50005 */
	/** Controllers bound to different mount points */
	ECGROUPMULTIMOUNTED,		/* 50006 */
	/* This is the stock error. Default error. @todo really? */
	ECGROUPNOTALLOWED,		/* 50007 */
	ECGMAXVALUESEXCEEDED,		/* 50008 */
	ECGCONTROLLEREXISTS,		/* 50009 */
	ECGVALUEEXISTS,			/* 50010 */
	ECGINVAL,			/* 50011 */
	ECGCONTROLLERCREATEFAILED,	/* 50012 */
	ECGFAIL,			/* 50013 */
	ECGROUPNOTINITIALIZED,		/* 50014 */
	ECGROUPVALUENOTEXIST,		/* 50015 */
	/**
	 * Represents error coming from other libraries like glibc. @c libcgroup
	 * users need to check cgroup_get_last_errno() upon encountering this
	 * error.
	 */
	ECGOTHER,			/* 50016 */
	ECGROUPNOTEQUAL,		/* 50017 */
	ECGCONTROLLERNOTEQUAL,		/* 50018 */
	/** Failed to parse rules configuration file. */
	ECGROUPPARSEFAIL,		/* 50019 */
	/** Rules list does not exist. */
	ECGROUPNORULES,			/* 50020 */
	ECGMOUNTFAIL,			/* 50021 */
	/**
	 * Not an real error, it just indicates that iterator has come to end
	 * of sequence and no more items are left.
	 */
	ECGEOF = 50023,
	/** Failed to parse config file (cgconfig.conf). */
	ECGCONFIGPARSEFAIL,		/* 50024 */
	ECGNAMESPACEPATHS,		/* 50025 */
	ECGNAMESPACECONTROLLER,		/* 50026 */
	ECGMOUNTNAMESPACE,		/* 50027 */
	ECGROUPUNSUPP,			/* 50028 */
	ECGCANTSETVALUE,		/* 50029 */
	/** Removing of a group failed because it was not empty. */
	ECGNONEMPTY,			/* 50030 */
	/** Failed to convert from cgroup v1 to/from cgroup v2 */
	ECGNOVERSIONCONVERT,		/* 50031 */
};

/**
 * Legacy definition of ECGRULESPARSEFAIL error code.
 */
#define ECGRULESPARSEFAIL	ECGROUPPARSEFAIL

/**
 * Format error code to a human-readable English string. No internationalization
 * is currently done. Returned pointer leads to @c libcgroup memory and
 * must not be freed nor modified. The memory is rewritten by subsequent
 * call to this function.
 * @param code Error code for which the corresponding error string is
 * returned. When #ECGOTHER is used, text with glibc's description of
 * cgroup_get_last_errno() value is returned.
 */
const char *cgroup_strerror(int code);

/**
 * Return last errno, which caused ECGOTHER error.
 */
int cgroup_get_last_errno(void);

/**
 * @}
 * @}
 */
#ifdef __cplusplus
} /* extern "C" */
#endif

#endif /* _LIBCGROUP_INIT_H */
