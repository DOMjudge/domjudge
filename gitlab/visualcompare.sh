#!/bin/bash
# https://askubuntu.com/questions/209517/does-diff-exist-for-images

set -euxo pipefail
export PS4='(${BASH_SOURCE}:${LINENO}): - [$?] $ '

failingchanges="failingchanges"
predictedchanges="predictedchanges"

mkdir -p {$failingchanges,$predictedchanges}

for file in `ls screenshotspr`
do
    PR=screenshotspr/$file
    MR=screenshotsmaster/$file
    # This fails when there is a change in time between the branches
    set +e
    DIFF=`idiff -warn 100 -fail 0.5 $PR $MR -abs -od -scale 10.0`;RET=$?
    set -e
    # There is the problem of detecting if a change is wanted or unwanted, currently check all the captured
    # screenshots against master for changes
    if [ $RET -ne 0 ]; then
        REMOVE=".html-ff.png"
        GITHUB_PR=`cut -d '/' -f1 <<< ${CI_COMMIT_BRANCH##pr-}`
        ENDPOINT=${file/$REMOVE}
        WANTED=`python3 gitlab/visualgithubprdiscussion.py $ENDPOINT $GITHUB_PR`
        if [ $WANTED = "wanted" ]; then
            STORDIR=$predictedchanges
        elif [ $WANTED = "none" ]; then
            STORDIR=$failingchanges
        fi
        compare $PR $MR -highlight-color blue $STORDIR/$file || true
    fi
done

CHANGE=0
for dir in $failingchanges $predictedchanges
do
    if [ $dir == $failingchanges ]; then
        STATE="failure"
    else
        STATE="success"
    fi
    MANY=`ls $dir|wc -l`
    FILE="$dir"
    if [ $MANY -eq 1 ]; then
        FILE=$dir/`ls $dir`
        CHANGE=1
    elif [ $MANY -gt 1 ]; then
        CHANGE=1
    fi
    if [ $MANY -gt 0 ]; then
      # Copied from CCS
      curl https://api.github.com/repos/domjudge/domjudge/statuses/$CI_COMMIT_SHA \
        -X POST \
        -H "Authorization: token $GH_BOT_TOKEN_OBSCURED" \
        -H "Accept: application/vnd.github.v3+json" \
        -d "{\"state\": \"$STATE\", \"target_url\": \"$CI_JOB_URL/artifacts/file/$FILE\", \"description\":\"UI changes\", \"context\": \"UI diffs ($dir)\"}"
    fi
done

if [ $CHANGE -eq 0 ]; then
    curl https://api.github.com/repos/domjudge/domjudge/statuses/$CI_COMMIT_SHA \
        -X POST \
        -H "Authorization: token $GH_BOT_TOKEN_OBSCURED" \
        -H "Accept: application/vnd.github.v3+json" \
        -d "{\"state\": \"success\", \"target_url\": \"$CI_JOB_URL/artifacts/browse/$FILE\", \"description\":\"No UI changes\", \"context\": \"UI diffs (None)\"}"
fi

