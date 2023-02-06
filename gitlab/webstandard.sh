#!/bin/bash

. gitlab/ci_settings.sh

section_start_collap setup "Setup and install"

export version=7.4

# Set up
"$( dirname "${BASH_SOURCE[0]}" )"/base.sh

trap log_on_err ERR

cd /opt/domjudge/domserver

section_end setup

section_start_collap testuser "Setup the test user"
# We're using the admin user in all possible roles
echo "DELETE FROM userrole WHERE userid=1;" | mysql domjudge
ADMINPASS=$(cat etc/initial_admin_password.secret)
export COOKIEJAR
COOKIEJAR=$(mktemp --tmpdir)
export CURLOPTS="--fail -sq -m 30 -b $COOKIEJAR"
if [ $ROLE = "public" ]; then
    ADMINPASS="failedlogin"
elif [ $ROLE = "team" ]; then
    # Add team to admin user
    echo "INSERT INTO userrole (userid, roleid) VALUES (1, 3);" | mysql domjudge
    echo "UPDATE user SET teamid = 1 WHERE userid = 1;" | mysql domjudge
elif [ $ROLE = "jury" ]; then
    # Add jury to admin user
    echo "INSERT INTO userrole (userid, roleid) VALUES (1, 2);" | mysql domjudge
elif [ $ROLE = "balloon" ]; then
    # Add balloon to admin user
    echo "INSERT INTO userrole (userid, roleid) VALUES (1, 4);" | mysql domjudge
elif [ $ROLE = "admin" ]; then
    # Add admin to admin user
    echo "INSERT INTO userrole (userid, roleid) VALUES (1, 1);" | mysql domjudge
fi

# Make an initial request which will get us a session id, and grab the csrf token from it
CSRFTOKEN=$(curl $CURLOPTS -c $COOKIEJAR "http://localhost/domjudge/login" 2>/dev/null | sed -n 's/.*_csrf_token.*value="\(.*\)".*/\1/p')
# Make a second request with our session + csrf token to actually log in
curl $CURLOPTS -c $COOKIEJAR -F "_csrf_token=$CSRFTOKEN" -F "_username=admin" -F "_password=$ADMINPASS" "http://localhost/domjudge/login"

cd $DIR

cp $COOKIEJAR cookies.txt
sed -i 's/#HttpOnly_//g' cookies.txt
sed -i 's/\t0\t/\t1999999999\t/g' cookies.txt
section_end testuser

# Could try different entrypoints
FOUNDERR=0
URL=public
mkdir $URL
cd $URL
cp $DIR/cookies.txt ./
section_start_collap scrape "Scrape the site with the rebuild admin user"
set +e
wget \                                                                                                       --reject-regex logout \                                                                                 --recursive \                                                                                           --no-clobber \                                                                                          --page-requisites \                                                                                     --html-extension \                                                                                      --convert-links \                                                                                       --restrict-file-names=windows \                                                                         --domains localhost \                                                                                   --no-parent \                                                                                           --load-cookies cookies.txt \                                                                                http://localhost/domjudge/$URL
RET=$?
set -e
#https://www.gnu.org/software/wget/manual/html_node/Exit-Status.html
# Exit code 4 is network error which we can ignore
if [ $RET -ne 4 ] && [ $RET -ne 0 ]; then
    exit $RET
fi
section_end scrape

if [ "$TEST" = "w3cval" ]; then
    section_start_collap upstream_problems "Remove files from upstream with problems"
    rm -rf localhost/domjudge/doc
    rm -rf localhost/domjudge/css/fontawesome-all.min.css*
    rm -rf localhost/domjudge/bundles/nelmioapidoc*
    rm -f localhost/domjudge/css/bootstrap.min.css*
    rm -f localhost/domjudge/css/select2-bootstrap.min.css*
    rm -f localhost/domjudge/jury/config/check/phpinfo*
    section_end upstream_problems

    section_start_collap test_suite "Install testsuite"
    cd $DIR
    wget https://github.com/validator/validator/releases/latest/download/vnu.linux.zip
    unzip -q vnu.linux.zip
    section_end test_suite
    FLTR='--filterpattern .*autocomplete.*|.*style.*'
    for typ in html css svg
    do
        $DIR/vnu-runtime-image/bin/vnu --errors-only --exit-zero-always --skip-non-$typ --format json $FLTR $URL 2> result.json
        NEWFOUNDERRORS=`$DIR/vnu-runtime-image/bin/vnu --errors-only --exit-zero-always --skip-non-$typ --format gnu $FLTR $URL 2>&1 | wc -l`
        FOUNDERR=$((NEWFOUNDERRORS+FOUNDERR))
        python3 -m "json.tool" < result.json > w3c$typ$URL.json
        trace_off; python3 gitlab/jsontogitlab.py w3c$typ$URL.json; trace_on
    done
else
    section_start_collap upstream_problems "Remove files from upstream with problems"
    rm -rf localhost/domjudge/{doc,api}
    section_end upstream_problems

    if [ $TEST == "axe" ]; then
        STAN="-e $TEST"
        FLTR=""
    else
        STAN="-s $TEST"
        FLTR="-E '#DataTables_Table_0 > tbody > tr > td > a','#menuDefault > a','#filter-card > div > div > div > span > span:nth-child(1) > span > ul > li > input','.problem-badge'"
    fi
    cd $DIR
    ACCEPTEDERR=5
    # shellcheck disable=SC2044,SC2035
    for file in `find $URL -name *.html`
    do
        section_start ${file//\//} $file
        # T is reasonable amount of errors to allow to not break
        su domjudge -c "/node_modules/.bin/pa11y $STAN -T $ACCEPTEDERR $FLTR --reporter json ./$file" | python3 -m json.tool
        ERR=`su domjudge -c "/node_modules/.bin/pa11y $STAN -T $ACCEPTEDERR $FLTR --reporter csv ./$file" | wc -l`
        FOUNDERR=$((ERR+FOUNDERR-1)) # Remove header row
        section_end $file
    done
fi
echo "Found: " $FOUNDERR
[ "$FOUNDERR" -eq 0 ]
