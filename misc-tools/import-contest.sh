#!/bin/bash
# Convenience script to import a contest (including metadata, teams and
# problems) via the command line. Requires httpie and jq to be installed and
# .netrc to be set up. See also https://www.domjudge.org/docs/manual/main/import.html

set -euo pipefail

if [ $# -eq 0 ]; then
    echo "Usage: $0 <domjudge-api-url>"
    exit 1
fi
api_url="$1"

myhttp() {
    # Add --verify=no here if you want to drop SSL verification during upload.
    http --check-status "$@"
}

if ! command -v http 1>/dev/zero; then
    echo "Please install httpie."
    exit 1
fi

if [ -r groups.json ]; then
    read -r -p "Import groups (from groups.json)? [y/N] " response
    response=${response,,}
    if [[ $response =~ ^(yes|y| ) ]]; then
        echo "Importing groups."
        myhttp -b -f POST "$api_url/users/groups" json@groups.json
    else
        echo "Skipping groups import."
    fi
elif [ -r groups.tsv ]; then
    read -r -p "Import groups (from groups.tsv)? [y/N] " response
    response=${response,,}
    if [[ $response =~ ^(yes|y| ) ]]; then
        echo "Importing groups."
        myhttp -b -f POST "$api_url/users/groups" tsv@groups.tsv
    else
        echo "Skipping groups import."
    fi
else
    echo "Neither 'groups.json' nor 'groups.tsv' found, skipping groups import."
fi

if [ -r organizations.json ]; then
    read -r -p "Import organizations (from organization.json)? [y/N] " response
    response=${response,,}
    if [[ $response =~ ^(yes|y| ) ]]; then
        echo "Importing organizations."
        myhttp -b -f POST "$api_url/users/organizations" json@organizations.json
    else
        echo "Skipping organizations import."
    fi
else
    echo "'organizations.json' not found, skipping organizations import."
fi

if [ -r teams.json ]; then
    read -r -p "Import teams (from teams.json)? [y/N] " response
    response=${response,,}
    if [[ $response =~ ^(yes|y| ) ]]; then
        echo "Importing teams."
        myhttp -b -f POST "$api_url/users/teams" json@teams.json
    else
        echo "Skipping teams import."
    fi
elif [ -r teams2.tsv ]; then
    read -r -p "Import teams (from teams2.tsv)? [y/N] " response
    response=${response,,}
    if [[ $response =~ ^(yes|y| ) ]]; then
        echo "Importing teams."
        myhttp -b -f POST "$api_url/users/teams" tsv@teams2.tsv
    else
        echo "Skipping teams import."
    fi
else
    echo "Neither 'teams.json' nor 'teams2.tsv' found, skipping teams import."
fi

if [ -r accounts.json ]; then
    read -r -p "Import accounts (from accounts.json)? [y/N] " response
    response=${response,,}
    if [[ $response =~ ^(yes|y| ) ]]; then
        echo "Importing accounts."
        myhttp -b -f POST "$api_url/users/accounts" json@accounts.json
    else
        echo "Skipping accounts import."
    fi
elif [ -r accounts.yaml ]; then
    read -r -p "Import accounts (from accounts.yaml)? [y/N] " response
    response=${response,,}
    if [[ $response =~ ^(yes|y| ) ]]; then
        echo "Importing accounts."
        myhttp -b -f POST "$api_url/users/accounts" yaml@accounts.yaml
    else
        echo "Skipping accounts import."
    fi
elif [ -r accounts.tsv ]; then
    read -r -p "Import accounts (from accounts.tsv)? [y/N] " response
    response=${response,,}
    if [[ $response =~ ^(yes|y| ) ]]; then
        echo "Importing accounts."
        myhttp -b -f POST "$api_url/users/accounts" tsv@accounts.tsv
    else
        echo "Skipping accounts import."
    fi
else
    echo "Neither 'accounts.json', 'accounts.yaml' nor 'groups.tsv' found, skipping accounts import."
fi
if [ -r accounts.tsv ]; then
    read -r -p "Import accounts (from accounts.tsv)? [Y/n] " response
    response=${response,,}
    if [[ $response =~ ^(yes|y| ) ]] || [[ -z $response ]]; then
        echo "Importing accounts."
        myhttp -b -f POST "$api_url/users/accounts" tsv@accounts.tsv
    else
        echo "Skipping accounts import."
    fi
else
    echo "'accounts.tsv' not found, skipping accounts import."
fi

if [ -r contest.yaml ] || [ -r contest.json ]; then
    if [ -r contest.yaml ]; then
        if [ -r problemset.yaml ]; then
            read -r -p "Import contest metadata (from contest.yaml and problemset.yaml)? [Y/n] " response
        else
            read -r -p "Import contest metadata (from contest.yaml)? [Y/n] " response
        fi
    else
        read -r -p "Import contest metadata (from contest.json)? [Y/n] " response
    fi
    response=${response,,}
    if [[ $response =~ ^(yes|y| ) ]] || [[ -z $response ]]; then
        echo "Importing contest."
        if [ -r contest.json ]; then
            cid=$(myhttp -b -f POST "$api_url/contests" json@contest.json | jq -r '.')
        else
            if [ -r problemset.yaml ]; then
                cat contest.yaml problemset.yaml > combined.yaml
                cid=$(myhttp -b -f POST "$api_url/contests" yaml@combined.yaml | jq -r '.')
                rm combined.yaml
            else
                cid=$(myhttp -b -f POST "$api_url/contests" yaml@contest.yaml | jq -r '.')
            fi
        fi
        echo "  -> cid=$cid"
    fi
else
    echo "Neither 'contest.yaml' nor 'contest.json' found, skipping contest metadata import."
fi

if [ -r problems.yaml ] || [ -r problems.json ]; then
    if [ -r problems.yaml ]; then
        read -r -p "Import problem metadata (from problems.yaml)? [Y/n] " response
    else
        read -r -p "Import problem metadata (from problems.json)? [Y/n] " response
    fi
    response=${response,,}
    if [[ $response =~ ^(yes|y| ) ]] || [[ -z $response ]]; then
        echo "Importing problem metadata."
        if [ -z "${cid+x}" ]; then
            read -r -p "Please specify the contest id: " cid
        fi
        if [ -r problems.json ]; then
            myhttp --quiet -b -f POST "$api_url/contests/$cid/problems/add-data" data@problems.json
        else
            myhttp --quiet -b -f POST "$api_url/contests/$cid/problems/add-data" data@problems.yaml
        fi
    else
        echo "Skipping problem metadata import."
    fi
else
    echo "Neither 'problems.yaml' nor 'problems.json' found, skipping problem metadata import."
fi

if [ -r problems.yaml ] || [ -r problems.json ] || [ -r problemset.yaml ]; then
    read -r -p "Import problems? [Y/n] " response
    response=${response,,}
    if [[ $response =~ ^(yes|y| ) ]] || [[ -z $response ]]; then
        set +e
        if [ "$(myhttp --pretty=format "$api_url/user" | jq  '.team')" = "null" ]; then
            read -r -p "No team associated with your account. Jury submissions won't be imported. Really continue? [y/N] " response
            response=${response,,}
            if [[ ! $response =~ ^(yes|y| ) ]]; then
                exit 2
            fi
        fi
        set -e

        echo "Importing problems."
        if [ -z "${cid+x}" ]; then
            read -r -p "Please specify the contest id: " cid
        fi
        if [ -r problems.yaml ]; then
            probs=$(grep -oP "(?<=id:\s)\s*[',\"]?[[:alnum:]]*[',\"]?(?=,|$)" problems.yaml)
        elif [ -r problems.json ]; then
            probs=$(jq -r '.[].id' < problems.json)
        else
            probs=$(grep -oP "(?<=short-name:\s)[',\"]?[[:alnum:]]*[',\"]?(?=,|$)" problemset.yaml)
        fi
        for prob in $probs; do
            prob="${prob//[,\',\"]/}"
            echo "Preparing problem '$prob'."
            if [ -r "${prob}.zip" ]; then
                echo "Deleting old zipfile."
                rm "${prob}.zip"
            fi
            if [ ! -d "$prob" ] && [ ! -r "$prob/problem.yaml" ]; then
                echo "Problem directory not found or doesn't contain a problem.yaml."
            fi
            (
                cd "$prob"
                zip -r "../$prob" -- .timelimit *
            )
            probid=$(myhttp --pretty=format "$api_url/contests/$cid/problems" | jq -r ".[] | select(.externalid==\"$prob\").id")
            read -r -p "Ready to import problem '$prob' to probid=$probid. Continue? [Y/n] " response
            response=${response,,}
            if [[ $response =~ ^(yes|y| ) ]] || [[ -z $response ]]; then
                myhttp --timeout 3000 -f POST "$api_url/contests/$cid/problems" zip@"${prob}.zip" problem="$probid"
            fi
        done
    else
        echo "Skipping contest import."
    fi
else
    echo "Neither 'problems.yaml', 'problems.json' nor 'problemset.yaml' found, skipping problems import."
fi
