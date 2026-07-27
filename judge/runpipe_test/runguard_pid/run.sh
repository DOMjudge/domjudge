#!/usr/bin/env bash

# When the solution is wrapped in `sudo ... runguard ... -- <program>`, runpipe
# passes its own pid to runguard with -U, so that runguard can report a time
# limit back. That option only reaches runguard if it is placed before the `--`
# that ends runguard's option list; appended after it, runguard would hand it to
# the contestant program instead and never learn the pid.
#
# Stand-ins for sudo and runguard record the arguments they are called with, so
# that this can be checked without needing root.

[[ $# != 1 ]] && echo "Usage: $0 runpipe" && exit 2

RUNPIPE="$(realpath "$1")"
STUBS="$(mktemp -d)"
trap 'rm -rf "$STUBS"' EXIT

cat > "$STUBS/sudo" <<'EOF'
#!/usr/bin/env bash
[[ "$1" == "-n" ]] && shift
exec "$@"
EOF

cat > "$STUBS/runguard" <<'EOF'
#!/usr/bin/env bash
printf '%s\n' "$@" > "$RUNGUARD_ARGV"
# Drop our own options and run whatever follows the separator.
while [[ $# -gt 0 && "$1" != "--" ]]; do shift; done
shift
exec "$@"
EOF

chmod +x "$STUBS/sudo" "$STUBS/runguard"

export RUNGUARD_ARGV="$STUBS/argv.txt"
PATH="$STUBS:$PATH" "$RUNPIPE" -o output.txt \
	./judge 42 = sudo -n "$STUBS/runguard" --walltime=5 -- ./solution 42
ret=$?

if [[ $ret != 42 ]]; then
	printf "\033[31;1mExpecting code 42, got %s\033[0m\n" "$ret"
	exit 1
fi

if [[ ! -f "$RUNGUARD_ARGV" ]]; then
	printf "\033[31;1mrunguard was never invoked\033[0m\n"
	exit 1
fi

mapfile -t argv < "$RUNGUARD_ARGV"

# Locate the -U option and the separator that ends runguard's options.
u_index=-1
sep_index=-1
for i in "${!argv[@]}"; do
	[[ "${argv[$i]}" == "-U" && $u_index == -1 ]] && u_index=$i
	[[ "${argv[$i]}" == "--" && $sep_index == -1 ]] && sep_index=$i
done

if [[ $u_index == -1 ]]; then
	printf "\033[31;1mrunguard was not passed -U at all: %s\033[0m\n" "${argv[*]}"
	exit 1
fi

if [[ $sep_index != -1 && $u_index -gt $sep_index ]]; then
	printf "\033[31;1m-U is behind the '--' separator, so runguard never sees it: %s\033[0m\n" "${argv[*]}"
	exit 1
fi

if ! [[ "${argv[$((u_index + 1))]}" =~ ^[0-9]+$ ]]; then
	printf "\033[31;1m-U is not followed by a pid: %s\033[0m\n" "${argv[*]}"
	exit 1
fi

# Everything after the separator is the command, which must be untouched.
command=( "${argv[@]:$((sep_index + 1))}" )
if [[ "${command[*]}" != "./solution 42" ]]; then
	printf "\033[31;1mthe command was altered, expected './solution 42', got '%s'\033[0m\n" "${command[*]}"
	exit 1
fi

printf "\033[32;1mok\033[0m\n"
