// default_validator from kattis problemtools package
// licensed under MIT license
//
// modified: float comparison
#include <fstream>
#include <iostream>
#include <string>
#include <cstdio>
#include <cstdlib>
#include <cstring>
#include <cassert>
#include <cmath>
#include <cstdarg>
#include <cctype>

const int EXIT_AC = 42;
const int EXIT_WA = 43;

std::ifstream judgein, judgeans;
FILE *judgemessage = NULL;
FILE *diffpos = NULL;
int judgeans_pos, stdin_pos;
int judgeans_line, stdin_line;

/* The floating point type we use internally: */
typedef long double flt;

void wrong_answer(const char *err, ...) {
	va_list pvar;
	va_start(pvar, err);
	fprintf(judgemessage, "Wrong answer on line %d of output (corresponding to line %d in answer file)\n",
			stdin_line, judgeans_line);
	vfprintf(judgemessage, err, pvar);
	fprintf(judgemessage, "\n");
	if (diffpos) {
		fprintf(diffpos, "%d %d", judgeans_pos, stdin_pos);
	}
	exit(EXIT_WA);
}

void judge_error(const char *err, ...) {
	va_list pvar;
	va_start(pvar, err);
	// If judgemessage hasn't been set up yet, write error to stderr
	if (!judgemessage) judgemessage = stderr;
	vfprintf(judgemessage, err, pvar);
	fprintf(judgemessage, "\n");
	assert(!"Judge Error");
}

bool isfloat(const char *s, flt &val) {
	char trash[20];
	flt v;
	if (sscanf(s, "%Lf%10s", &v, trash) != 1) return false;
	val = v;
	return true;
}

template <typename Stream>
void openfile(Stream &stream, const char *file, const char *whoami) {
	stream.open(file);
	if (stream.fail()) {
		judge_error("%s: failed to open %s\n", whoami, file);
	}
}

FILE *openfeedback(const char *feedbackdir, const char *feedback, const char *whoami) {
	std::string path = std::string(feedbackdir) + "/" + std::string(feedback);
	FILE *res = fopen(path.c_str(), "w");
	if (!res) {
		judge_error("%s: failed to open %s for writing", whoami, path.c_str());
	}
	return res;
}

// The behavior of std::tolower on (signed) char is undefined.
char tolower_char(char c)
{
    return static_cast<char>(std::tolower(static_cast<unsigned char>(c)));
}

bool equal_case_insensitive(std::string a, std::string b)
{
	for (char &c : a) c = tolower_char(c);
	for (char &c : b) c = tolower_char(c);

	return a==b;
}

/* Test two floating-point numbers for equality, accounting for +/-INF, NaN, and precision.
 * Float `jval` is considered the reference value for relative error.
 */
void compare_float(const std::string &judge, const std::string &team, flt jval, flt tval, flt float_abs_tol, flt float_rel_tol, const std::string &extra_msg) {
	/* Finite values are compared with some tolerance */
	if (std::isfinite(tval) && std::isfinite(jval)) {
		flt absdiff = fabsl(tval-jval);
		flt reldiff = fabsl((tval-jval)/jval);
		if (float_abs_tol >= 0 && float_rel_tol >= 0) {
			if (absdiff > float_abs_tol && reldiff > float_rel_tol) {
				wrong_answer("Too large difference.\n Judge: %s\n Team: %s\n Absolute difference: %Lg (tolerance: %Lg)\n Relative difference: %Lg (tolerance: %Lg)%s",
				             judge.c_str(), team.c_str(),
				             absdiff, float_abs_tol,
				             reldiff, float_rel_tol,
				             extra_msg.c_str());
			}
		} else if (float_abs_tol >= 0) {
			if (absdiff > float_abs_tol) {
				wrong_answer("Too large difference.\n Judge: %s\n Team: %s\n Absolute difference: %Lg (tolerance: %Lg)%s",
				             judge.c_str(), team.c_str(), absdiff, float_abs_tol, extra_msg.c_str());
			}
		} else if (float_rel_tol >= 0) {
			if (reldiff > float_rel_tol) {
				wrong_answer("Too large difference.\n Judge: %s\n Team: %s\n Relative difference: %Lg (tolerance: %Lg)%s",
				             judge.c_str(), team.c_str(), reldiff, float_rel_tol, extra_msg.c_str());
			}
		}
	/* NaN is equal to NaN */
	} else if (std::isnan(jval) && std::isnan(tval)) {
		return;
	/* Infinite values are equal if their sign matches */
	} else if (std::isinf(jval) && std::isinf(tval)) {
		if (std::signbit(jval) != std::signbit(tval)) {
			wrong_answer("Expected float %s, got: %s%s", judge.c_str(), team.c_str(), extra_msg.c_str());
		}
	/* Values in different classes are always different. */
	} else {
		wrong_answer("Expected float %s, got: %s%s", judge.c_str(), team.c_str(), extra_msg.c_str());
	}
}

const char *USAGE = "Usage: %s judge_in judge_ans feedback_dir [options] < team_out";

int main(int argc, char **argv) {
	if (argc < 4) {
		judge_error(USAGE, argv[0]);
	}
	judgemessage = openfeedback(argv[3], "judgemessage.txt", argv[0]);
	diffpos = openfeedback(argv[3], "diffposition.txt", argv[0]);
	openfile(judgein, argv[1], argv[0]);
	openfile(judgeans, argv[2], argv[0]);

	bool case_sensitive = false;
	bool space_change_sensitive = false;
	bool use_floats = false;
	flt float_abs_tol = -1;
	flt float_rel_tol = -1;

	for (int a = 4; a < argc; ++a) {
		if        (!strcmp(argv[a], "case_sensitive")) {
			case_sensitive = true;
		} else if (!strcmp(argv[a], "space_change_sensitive")) {
			space_change_sensitive = true;
		} else if (!strcmp(argv[a], "float_absolute_tolerance")) {
			if (a+1 == argc || !isfloat(argv[a+1], float_abs_tol))
				judge_error(USAGE, argv[0]);
			++a;
		} else if (!strcmp(argv[a], "float_relative_tolerance")) {
			if (a+1 == argc || !isfloat(argv[a+1], float_rel_tol))
				judge_error(USAGE, argv[0]);
			++a;
		} else if (!strcmp(argv[a], "float_tolerance")) {
			if (a+1 == argc || !isfloat(argv[a+1], float_rel_tol))
				judge_error(USAGE, argv[0]);
			float_abs_tol = float_rel_tol;
			++a;
		} else {
			judge_error(USAGE, argv[0]);
		}
	}
	use_floats = float_abs_tol >= 0 || float_rel_tol >= 0;

	judgeans_pos = stdin_pos;
	judgeans_line = stdin_line = 1;

	std::string judge, team;
	while (true) {
		// Space!  Can't live with it, can't live without it...
		while (std::isspace(static_cast<unsigned char>(judgeans.peek()))) {
			char c = (char)judgeans.get();
			if (space_change_sensitive) {
				int d = std::cin.get();
				if (c != d) {
					wrong_answer("Space change error: got %d expected %d", d, c);
				}
				if (d == '\n') ++stdin_line;
				++stdin_pos;
			}
			if (c == '\n') ++judgeans_line;
			++judgeans_pos;
		}
		while (std::isspace(static_cast<unsigned char>(std::cin.peek()))) {
			char d = (char)std::cin.get();
			if (space_change_sensitive) {
				wrong_answer("Space change error: judge out of space, got %d from team", d);
			}
			if (d == '\n') ++stdin_line;
			++stdin_pos;
		}

		if (!(judgeans >> judge))
			break;

		if (!(std::cin >> team)) {
			wrong_answer("User EOF while judge had more output\n(Next judge token: %s)", judge.c_str());
		}

		std::string extra_msg = "";
		bool nonprintable = false;
		for (char c : judge) {
			if (!std::isprint(static_cast<unsigned char>(c))) {
				nonprintable = true;
				extra_msg += "judge";
				break;
			}
		}
		for (char c : team) {
			if (!std::isprint(static_cast<unsigned char>(c))) {
				if (nonprintable) extra_msg += ',';
				nonprintable = true;
				extra_msg += "team";
				break;
			}
		}
		if (nonprintable) {
			extra_msg = "\nNote: " + extra_msg + " token contains non-printable characters";
		}

		flt jval, tval;
		if (use_floats && isfloat(judge.c_str(), jval)) {
			if (!isfloat(team.c_str(), tval)) {
				wrong_answer("Expected float, got: %s%s", team.c_str(), extra_msg.c_str());
			}
			compare_float(judge, team, jval, tval, float_abs_tol, float_rel_tol, extra_msg);
		} else if (case_sensitive) {
			if (judge != team) {
				wrong_answer("String tokens mismatch\nJudge: \"%s\"\nTeam: \"%s\"%s",
				             judge.c_str(), team.c_str(), extra_msg.c_str());
			}
		} else {
			if (!equal_case_insensitive(judge, team)) {
				wrong_answer("String tokens mismatch\nJudge: \"%s\"\nTeam: \"%s\"%s",
				             judge.c_str(), team.c_str(), extra_msg.c_str());
			}
		}
		judgeans_pos += judge.length();
		stdin_pos += team.length();
	}

	if (std::cin >> team) {
		wrong_answer("Trailing output:\n%s", team.c_str());
	}

	exit(EXIT_AC);
}
