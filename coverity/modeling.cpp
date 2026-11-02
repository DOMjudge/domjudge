/* To be uploaded to scan.coverity.com as modeling file to exclude
 * false positives because it does not detect that error() always
 * terminates the program.
 */

#include <format>

void error(int errnum, const char *format, ...) {
	__coverity_panic__();
}

template<typename... Args>
void die(int errnum, std::format_string<Args...> fmt, Args&&... args) {
	__coverity_panic__();
}
