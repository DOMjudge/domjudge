#!/bin/bash

# Run webstandard.sh for all role/test combinations against a single
# installation. The database is restored to its freshly installed state
# before each combination, as crawling can change it (e.g. the balloon
# role marks balloons as done).

. .github/jobs/ci_settings.sh

set -euo pipefail

COMBINATIONS="public:w3cval public:WCAG2AA team:w3cval team:WCAG2AA balloon:w3cval balloon:WCAG2AA jury:w3cval admin:w3cval"
LOGS=/tmp/webstandard-logs
DBDUMP=/tmp/webstandard-db.sql

# Move the artifacts and logs gathered so far to $1 and start with empty ones.
collect_logs () {
    DEST="$LOGS/$1"
    mkdir -p "$LOGS"
    mv "$ARTIFACTS" "$DEST"
    mkdir -p "$ARTIFACTS"
    for dir in /var/log/nginx /opt/domjudge/domserver/webapp/var/log; do
        mkdir -p "$DEST$dir"
        for log in "$dir"/*.log; do
            [ -f "$log" ] || continue
            cp "$log" "$DEST$dir/"
            : > "$log"
        done
    done
}

section_start "Save installed database"
collect_logs install
mysqldump --quick --max_allowed_packet=1024M domjudge > "$DBDUMP"
section_end

FAILED=""
for combination in $COMBINATIONS; do
    ROLE="${combination%:*}"
    TEST="${combination#*:}"

    section_start "Setup $ROLE for $TEST"
    rm -rf public cookies.txt result.json
    mysql --max_allowed_packet=1024M domjudge < "$DBDUMP"
    # We're using the admin user in all possible roles
    mysql_log "DELETE FROM userrole WHERE userid=1;" domjudge
    if [ "$ROLE" = "team" ]; then
        mysql_log "INSERT INTO userrole (userid, roleid) VALUES (1, 3);" domjudge
        mysql_log "UPDATE user SET teamid = 1 WHERE userid = 1;" domjudge
    elif [ "$ROLE" = "jury" ]; then
        mysql_log "INSERT INTO userrole (userid, roleid) VALUES (1, 2);" domjudge
    elif [ "$ROLE" = "balloon" ]; then
        mysql_log "INSERT INTO userrole (userid, roleid) VALUES (1, 4);" domjudge
    elif [ "$ROLE" = "admin" ]; then
        mysql_log "INSERT INTO userrole (userid, roleid) VALUES (1, 1);" domjudge
    fi
    section_end

    if .github/jobs/webstandard.sh "$TEST" "$ROLE"; then
        echo "Webstandard $TEST for $ROLE: OK"
    else
        echo "::error::Webstandard $TEST for $ROLE failed"
        FAILED="$FAILED $ROLE:$TEST"
    fi

    section_start "Collect logs for $ROLE $TEST"
    collect_logs "$ROLE-$TEST"
    section_end
done

if [ -n "$FAILED" ]; then
    echo "::error::Failed webstandard combinations:$FAILED"
    exit 1
fi
