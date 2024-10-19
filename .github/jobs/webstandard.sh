#!/bin/bash

. .github/jobs/ci_settings.sh

DIR="$PWD"

if [ "$#" -ne "2" ]; then
    exit 2
fi

TEST="$1"
ROLE="$2"

cd /opt/domjudge/domserver

section_start "Setup pa11y"
/home/domjudge/node_modules/.bin/pa11y --version
section_end

section_start "Setup the test user"
ADMINPASS=$(cat etc/initial_admin_password.secret)
export COOKIEJAR
COOKIEJAR=$(mktemp --tmpdir)
export CURLOPTS="--fail -sq -m 30 -b $COOKIEJAR"
if [ "$ROLE" = "public" ]; then
    ADMINPASS="failedlogin"
fi

# Make an initial request which will get us a session id, and grab the csrf token from it
CSRFTOKEN=$(curl $CURLOPTS -c $COOKIEJAR "http://localhost/domjudge/login" 2>/dev/null | sed -n 's/.*_csrf_token.*value="\(.*\)".*/\1/p')
# Make a second request with our session + csrf token to actually log in
# shellcheck disable=SC2086
curl $CURLOPTS -c "$COOKIEJAR" -F "_csrf_token=$CSRFTOKEN" -F "_username=admin" -F "_password=$ADMINPASS" "http://localhost/domjudge/login"

# Move back to the default directory
cd "$DIR"

cp "$COOKIEJAR" cookies.txt
sed -i 's/#HttpOnly_//g' cookies.txt
sed -i 's/\t0\t/\t1999999999\t/g' cookies.txt
section_end

# Could try different entrypoints
FOUNDERR=0
URL=public
mkdir "$URL"
cd "$URL"
cp "$DIR"/cookies.txt ./
section_start "Scrape the site with the rebuild admin user"
set +e
wget \
    --reject-regex logout \
    --recursive \
    --no-clobber \
    --page-requisites \
    --html-extension \
    --convert-links \
    --restrict-file-names=windows \
    --domains localhost \
    --no-parent \
    --load-cookies cookies.txt \
    http://localhost/domjudge/"$URL"
set -e
RET=$?
section_end

section_start "Archive downloaded site"
cp -r localhost $ARTIFACTS/
section_end

section_start "Analyse failures"
#https://www.gnu.org/software/wget/manual/html_node/Exit-Status.html
# Exit code 4 is network error which we can ignore
# Exit code 8 can also be because of HTTP404 or 400
if [ $RET -ne 4 ] && [ $RET -ne 0 ] && [ $RET -ne 8 ]; then
    exit $RET
fi

EXPECTED_HTTP_CODES="200\|302\|400\|404\|403"
if [ "$ROLE" = "public" ]; then
    # It's expected to encounter a 401 for the login page as we supply the wrong password
    EXPECTED_HTTP_CODES="$EXPECTED_HTTP_CODES\|401"
fi
set +e
NUM_ERRORS=$(grep -v "HTTP/1.1\" \($EXPECTED_HTTP_CODES\)" /var/log/nginx/domjudge.log | grep -v "robots.txt" -c; if [ "$?" -gt 1 ]; then exit 127; fi)
set -e
echo "$NUM_ERRORS"

if [ "$NUM_ERRORS" -ne 0 ]; then
    grep -v "HTTP/1.1\" \($EXPECTED_HTTP_CODES\)" /var/log/nginx/domjudge.log | grep -v "robots.txt"
    exit 1
fi
section_end

if [ "$TEST" = "none" ]; then
    exit $NUM_ERRORS
fi

if [ "$TEST" = "w3cval" ]; then
    section_start "Remove files from upstream with problems"
    rm -rf localhost/domjudge/doc
    rm -rf localhost/domjudge/css/fontawesome-all.min.css*
    rm -rf localhost/domjudge/bundles/nelmioapidoc*
    rm -f localhost/domjudge/css/bootstrap.min.css*
    rm -f localhost/domjudge/css/select2-bootstrap*.css*
    rm -f localhost/domjudge/css/dataTables*.css*
    rm -f localhost/domjudge/jury/config/check/phpinfo*
    section_end

    section_start "Install testsuite"
    cd "$DIR"
    wget https://github.com/validator/validator/releases/latest/download/vnu.linux.zip
    unzip -q vnu.linux.zip
    section_end

    FLTR='--filterpattern .*autocomplete.*|.*style.*|.*role=tab.*|.*descendant.*|.*Stray.*|.*attribute.*|.*Forbidden.*|.*stream.*|.*obsolete.*'
    for typ in html css svg
    do
        section_start "Analyse with $typ"
        # shellcheck disable=SC2086
        "$DIR"/vnu-runtime-image/bin/vnu --errors-only --exit-zero-always --skip-non-$typ --format json $FLTR "$URL" 2> result.json
        # shellcheck disable=SC2086
        NEWFOUNDERRORS=$("$DIR"/vnu-runtime-image/bin/vnu --errors-only --exit-zero-always --skip-non-$typ --format gnu $FLTR "$URL" 2>&1 | wc -l)
        FOUNDERR=$((NEWFOUNDERRORS+FOUNDERR))
        python3 -m "json.tool" < result.json > "$ARTIFACTS/w3c$typ$URL.json"
        trace_off; python3 gitlab/jsontogitlab.py "$ARTIFACTS/w3c$typ$URL.json"; trace_on
        section_end
    done
else
    section_start "Remove files from upstream with problems"
    rm -rf localhost/domjudge/{doc,api}
    section_end

    if [ "$TEST" == "axe" ]; then
        STAN="-e $TEST"
        FLTR=""
    else
        STAN="-s $TEST"
        FLTR0="-E '#DataTables_Table_0 > tbody > tr > td > a','#menuDefault > a','#filter-card > div > div > div > span > span:nth-child(1) > span > ul > li > input',.problem-badge"
        FLTR1="'html > body > div > div > div > div > div > div > table > tbody > tr > td > a > span','html > body > div > div > div > div > div > div > form > div > div > div > label'"
        FLTR="$FLTR0,$FLTR1"
    fi
    chown -R domjudge:domjudge "$DIR"
    cd "$DIR"
    ACCEPTEDERR=5
    # shellcheck disable=SC2044,SC2035
    for file in $(find $URL -name "*.html")
    do
        section_start "$file"
        su domjudge -c "/home/domjudge/node_modules/.bin/pa11y --config .github/jobs/pa11y_config.json $STAN -r json -T $ACCEPTEDERR $FLTR $file" | python3 -m json.tool
        ERR=$(su domjudge -c "/home/domjudge/node_modules/.bin/pa11y --config .github/jobs/pa11y_config.json $STAN -r csv -T $ACCEPTEDERR $FLTR $file" | wc -l)
        FOUNDERR=$((ERR+FOUNDERR-1)) # Remove header row
        section_end
    done
fi

echo "Found: " $FOUNDERR
[ "$FOUNDERR" -eq 0 ]
