shopt -s expand_aliases
alias trace_on='set -x'
alias trace_off='{ set +x; } 2>/dev/null'

function section_start_internal() {
	echo -e "section_start:`date +%s`:$2[collapsed=$1]\r\e[0K$3"
	trace_on
}

function section_end_internal() {
	echo -e "section_end:`date +%s`:$1\r\e[0K"
	trace_on
}

alias section_start_collap='trace_off ; section_start_internal true'
alias section_start='trace_off ; section_start_internal false'
alias section_end='trace_off ; section_end_internal '

LOGFILE="/opt/domjudge/domserver/webapp/var/log/prod.log"

function log_on_err() {
        echo -e "\\n\\n=======================================================\\n"
        echo "Symfony log:"
        if sudo test -f "$LOGFILE" ; then
                sudo cat "$LOGFILE"
        fi
}

