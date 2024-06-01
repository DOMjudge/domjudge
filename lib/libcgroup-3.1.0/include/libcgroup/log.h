/* SPDX-License-Identifier: LGPL-2.1-only */
#ifndef _LIBCGROUP_LOG_H
#define _LIBCGROUP_LOG_H

#ifndef _LIBCGROUP_H_INSIDE
#error "Only <libcgroup.h> should be included directly."
#endif

#ifndef SWIG
#include <features.h>
#endif

#include <stdarg.h>

#ifdef __cplusplus
extern "C" {
#endif

/**
 * @defgroup group_log 7. Logging
 * @{
 *
 * @name Logging
 * @{
 * Libcgroup allows applications to register a callback function which
 * libcgroup will call when it wants to log something. Each log message
 * has associated a log level. As described in previous chapter, most libcgroup
 * functions return an error code, which described root cause of the failure
 * and log messages might provide further details about these failures and other
 * notable events.
 *
 * @par
 * The logging callback can be set at any time, but setting the callback before
 * any other libcgroup function (including cgroup_init()) is highly recommended.
 * If no logger is set before cgroup_init() is called, default logger is
 * automatically set, logging CGROUP_LOG_ERROR messages to stdout.
 *
 * @par Setting log level
 * Some of the functions below set the log level as integer.
 * Application can set directly a value of enum #cgroup_log_level or use
 * value <tt>-1</tt> to set the log level automatically. In this case, libcgroup
 * inspects environment variable <tt>CGROUP_LOGLEVEL</tt> if it is set
 * and contains any of these values: <tt>ERROR</tt>, <tt>WARNING</tt>,
 * <tt>INFO</tt>, <tt>DEBUG</tt> or integer number representing value from
 * enum #cgroup_log_level. If <tt>CGROUP_LOGLEVEL</tt> is not set or its value
 * is not valid, <tt>CGROUP_LOG_ERROR</tt> is set as default log level.
 *
 * @par Example:
 * Following short example shows custom libcgroup logger sending all log
 * messages to <tt>stderr</tt>:
 * @code
 * static void my_logger(void *userdata, int level, const char *fmt, va_list ap)
 * {
 *	vfprintf(stderr, fmt, ap);
 * }
 *
 * int main(int argc, char **argv)
 * {
 *	int ret;
 *
 *	cgroup_set_logger(my_logger, -1, NULL);
 *	ret = cgroup_init();
 *	if (ret) {
 *		...
 *	}
 *	...
 * @endcode
 */

/**
 * Level of importance of a log message.
 */
enum cgroup_log_level {
	/**
	 * Continue printing the log message, with the previous log level.
	 * Used to print log messages without the line break.
	 */
	CGROUP_LOG_CONT = 0,
	/**
	 * Something serious happened and libcgroup failed to perform requested
	 * operation.
	 */
	CGROUP_LOG_ERROR,
	/**
	 * Something bad happened but libcgroup recovered from the error.
	 */
	CGROUP_LOG_WARNING,
	/**
	 * Something interesting happened and the message might be useful to the
	 * user.
	 */
	CGROUP_LOG_INFO,
	/**
	 * Debugging messages useful to libcgroup developers.
	 */
	CGROUP_LOG_DEBUG,
};

typedef void (*cgroup_logger_callback)(void *userdata, int level,
				       const char *fmt, va_list ap);

/**
 * Set libcgroup logging callback. All log messages with equal or lower log
 * level will be sent to the application's callback. There can be only
 * one callback logger set, the previous callback is replaced with the new one
 * by calling this function.
 * Use NULL as the logger callback to completely disable libcgroup logging.
 *
 * @param logger The callback.
 * @param loglevel The log level. Use value -1 to automatically discover the
 * level from CGROUP_LOGLEVEL environment variable.
 * @param userdata Application's data which will be provided back to the
 * callback.
 */
extern void cgroup_set_logger(cgroup_logger_callback logger, int loglevel,
			      void *userdata);

/**
 * Set libcgroup logging to stdout. All messages with the given loglevel
 * or below will be sent to standard output. Previous logger set by
 * cgroup_set_logger() is replaced.
 *
 * @param loglevel The log level. Use value -1 to automatically discover the
 * level from CGROUP_LOGLEVEL environment variable.
 */
extern void cgroup_set_default_logger(int loglevel);

/**
 * Change current loglevel.
 * @param loglevel The log level. Use value -1 to automatically discover the
 * level from CGROUP_LOGLEVEL environment variable.
 */
extern void cgroup_set_loglevel(int loglevel);

/**
 * Libcgroup log function. This is for applications which are too lazy to set
 * up their own complex logging and miss-use libcgroup for that purpose.
 * I.e. this function should be used only by simple command-line tools.
 * This logging automatically benefits from CGROUP_LOGLEVEL env. variable.
 */
extern void cgroup_log(int loglevel, const char *fmt, ...);

/**
 * Parse levelstr string for information about desired loglevel. The levelstr
 * is usually a value of the CGROUP_LOGLEVEL environment variable.
 * @param levelstr String containing desired loglevel.
 */
extern int cgroup_parse_log_level_str(const char *levelstr);

/**
 * @}
 * @}
 */
#ifdef __cplusplus
} /* extern "C" */
#endif

#endif /* _LIBCGROUP_LOG_H */
