#!/bin/bash
# https://askubuntu.com/questions/209517/does-diff-exist-for-images

set -euxo pipefail
export PS4='(${BASH_SOURCE}:${LINENO}): - [$?] $ '

failingchanges="failingchanges"
predictedchanges="predictedchanges"

mkdir -p {$failingchanges,$predictedchanges}

if grep "^pr-" <<< "$CI_COMMIT_BRANCH"; then
    GITHUB_PR=$(cut -d '/' -f1 <<< "${CI_COMMIT_BRANCH##pr-}")
else
    URLORG="https://api.github.com/repos/domjudge/domjudge"
    URL="commits/$CI_COMMIT_SHA/pulls"
    GITHUB_PR=$(curl -H \
        "Accept: 'application/vnd.github.groot-preview+json'" \
        $URLORG/$URL | awk '/"number": /{print $2}')
    GITHUB_PR=${GITHUB_PR%,}
fi

DIR=$(pwd)
ADDREMLOG="$DIR"/addrem.log
ADD=0
DEL=0
cd screenshotspr
for URL in ./*; do
    URL=${URL#.\/}
    SPR="/screenshotspr/$URL"
    SMR=${SPR/pr/wf2020}
    cd "$DIR"/"$SPR"/
    for ROLE in ./*; do
        ROLE=${ROLE#.\/}
        cd "$DIR"/"$SPR"/"$ROLE"
        for FILE in ./*; do
            FILE=${FILE#.\/}
            PR="$DIR"/"$SPR"/"$ROLE"/"$FILE"
            MR="$DIR"/"$SMR"/"$ROLE"/"$FILE"
            if test -f "$MR"; then
                # This fails when there is a change in time between the branches
                set +e
                idiff -warn 100 -fail 0.5 "$PR" "$MR" -abs -od -scale 10.0;RET=$?
                set -e
                # There is the problem of detecting if a change is wanted or unwanted, currently check all the captured
                # screenshots against wf2020 for changes
                if [ $RET -ne 0 ]; then
                    REMOVE=".html-ff.png"
                    ENDPOINT=${FILE/$REMOVE}
                    WANTED=$(python3 "$DIR"/gitlab/visualgithubprdiscussion.py "$ENDPOINT" "$GITHUB_PR")
                    if [ "$WANTED" = "wanted" ]; then
                        STORDIR="$DIR"/"$predictedchanges"
                    elif [ "$WANTED" = "none" ]; then
                        STORDIR="$DIR"/"$failingchanges"
                    fi
                    compare "$PR" "$MR" -highlight-color blue "$STORDIR"/"${ROLE}"_"${FILE}" || true
                    cp "$MR" "$STORDIR"/"${ROLE}"_wf2020_"${FILE}"
                    cp "$PR" "$STORDIR"/"${ROLE}"_pr_"${FILE}"
                fi
            else
                ADD=$((ADD+1))
                echo "Add: $FILE" >> "$ADDREMLOG"
            fi
        done
        cd "$DIR"/"$SMR"/"$ROLE"
        for FILE in ./*; do
            FILE=${FILE#.\/}
            PR="$DIR"/"$SPR"/"$ROLE"/"$FILE"
            MR="$DIR"/"$SMR"/"$ROLE"/"$FILE"
            if test ! -f "$PR"; then
                DEL=$((DEL+1))
                echo "Rem: $FILE" >> "$ADDREMLOG"
            fi
        done
    done
done

CHANGE=0
cd "$DIR"
for DIRCHANGES in "$predictedchanges" "$failingchanges"; do
    if [ "$DIRCHANGES" == "$failingchanges" ]; then
        STATE="failure"
    else
        STATE="success"
    fi
    MANY=$(ls $DIRCHANGES|wc -l)
    FILE="browse/$DIRCHANGES"
    if [ "$MANY" -eq 1 ]; then
        FILE="file/$DIRCHANGES/"$(ls $DIRCHANGES)
        CHANGE=1
    elif [ "$MANY" -gt 1 ]; then
        CHANGE=1
    fi
    if [ "$MANY" -gt 0 ]; then
        # Copied from CCS
        curl "https://api.github.com/repos/domjudge/domjudge/statuses/$CI_COMMIT_SHA" \
            -X POST \
            -H "Authorization: token $GH_BOT_TOKEN_OBSCURED" \
            -H "Accept: application/vnd.github.v3+json" \
            -d "{\"state\": \"$STATE\", \"target_url\": \"$CI_JOB_URL/artifacts/$FILE\", \"description\":\"UI changes ($DIRCHANGES) \", \"context\": \"UI diffs\"}"
        if [ $STATE == "failure" ]; then
            cat <<EOF > $failingchanges/README.txt
List the changed endpoints in a message on the PR. Regex is allowed.

Changed URLs:
changed_endpoint(as possible regex)
another_changed_endpoint(as possible regex)
EOF
            exit 1
        fi
    fi
done

if [ $CHANGE -eq 0 ]; then
    curl https://api.github.com/repos/domjudge/domjudge/statuses/"$CI_COMMIT_SHA" \
        -X POST \
        -H "Authorization: token $GH_BOT_TOKEN_OBSCURED" \
        -H "Accept: application/vnd.github.v3+json" \
        -d "{\"state\": \"success\", \"target_url\": \"$CI_JOB_URL/artifacts/browse/\", \"description\":\"No UI changes\", \"context\": \"UI diffs\"}"
fi

curl https://api.github.com/repos/domjudge/domjudge/statuses/"$CI_COMMIT_SHA" \
    -X POST \
    -H "Authorization: token $GH_BOT_TOKEN_OBSCURED" \
    -H "Accept: application/vnd.github.v3+json" \
    -d "{\"state\": \"success\", \"target_url\": \"$CI_JOB_URL/artifacts/browse/\", \"description\":\"Removed: $DEL, Added: $ADD\", \"context\": \"seen_urls\"}"

