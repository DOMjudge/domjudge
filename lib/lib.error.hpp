#ifndef LIB_ERROR_HPP
#define LIB_ERROR_HPP

#include <format>
#include <string>
#include <utility>
#include <cstring>
#include <cerrno>
#include <cstdio>
#include <cstdarg>
#include <string_view>

#ifdef HAVE_SYSLOG_H
#include <syslog.h>
#else
#error "Syslog header file not available."
#endif

#define ERRSTR    "error"
#define ERRMATCH  ERRSTR": "

#define WARNSTR   "warning"
#define WARNMATCH WARNSTR": "

extern const int exit_failure;

/* Import from the main program for logging purposes */
extern std::string_view progname;

/* Variables defining logmessages verbosity to stderr/logfile */
extern int  verbose;
extern int  loglevel;

// Internal backend function (exposed for templates)
void logmsg_str(int msglevel, const std::string& mesg);

/**
 * These functions accept std::format style strings and arguments.
 * Example: logmsg(LOG_INFO, "Value: {}", 42);
 */

template<typename... Args>
void logmsg(int level, std::format_string<Args...> fmt, Args&&... args) {
    try {
        std::string msg = std::format(fmt, std::forward<Args>(args)...);
        logmsg_str(level, msg);
    } catch (const std::format_error& e) {
        logmsg_str(LOG_ERR, std::string("Format error in logmsg: ") + e.what());
    }
}

// Helper for error/warning/logerror
template<typename... Args>
void log_helper(int level, const char* prefix, int errnum, std::format_string<Args...> fmt, Args&&... args) {
    try {
        std::string user_msg = std::format(fmt, std::forward<Args>(args)...);
        std::string err_descr;
        
        if (errnum != 0) {
            err_descr = std::strerror(errno);
        }

        std::string buffer;
        if (prefix) buffer += prefix;
        buffer += user_msg;
        if (!user_msg.empty() && !err_descr.empty()) buffer += ": ";
        if (!err_descr.empty()) buffer += err_descr;

        logmsg_str(level, buffer);
    } catch (const std::exception& e) {
        logmsg_str(LOG_ERR, std::string("Format error: ") + e.what());
    }
}

template<typename... Args>
void error(int errnum, std::format_string<Args...> fmt, Args&&... args) {
    log_helper(LOG_ERR, "error: ", errnum, fmt, std::forward<Args>(args)...);
    std::exit(exit_failure);
}

template<typename... Args>
void warning(int errnum, std::format_string<Args...> fmt, Args&&... args) {
    log_helper(LOG_WARNING, "warning: ", errnum, fmt, std::forward<Args>(args)...);
}

template<typename... Args>
void logerror(int errnum, std::format_string<Args...> fmt, Args&&... args) {
    log_helper(LOG_ERR, "error: ", errnum, fmt, std::forward<Args>(args)...);
}

#endif // LIB_ERROR_HPP
