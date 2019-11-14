#!/bin/bash

set -euo pipefail

if [ $# -eq 0 ]; then
  echo "Usage: $0 <domjudge-api-url>"
  exit -1
fi
api_url="$1"

read -r -p "Import groups? [y/N] " response
response=${response,,}
if [[ $response =~ ^(yes|y| ) ]]; then
  if [ ! -r groups.tsv ]; then
    echo "'groups.tsv' not found."
  else
    echo "Importing groups."
    http --check-status -b -f POST "$api_url/users/groups" tsv@groups.tsv
  fi
else
  echo "Skipping group import."
fi

read -r -p "Import teams? [y/N] " response
response=${response,,}
if [[ $response =~ ^(yes|y| ) ]]; then
  if [ ! -r teams2.tsv ]; then
    echo "'teams2.tsv' not found."
  else
    echo "Importing teams."
    http --check-status -b -f POST "$api_url/users/teams" tsv@teams2.tsv
  fi
else
  echo "Skipping teams import."
fi

read -r -p "Import accounts? [Y/n] " response
response=${response,,}
if [[ $response =~ ^(yes|y| ) ]] || [[ -z $response ]]; then
  if [ ! -r accounts.tsv ]; then
    echo "'accounts.tsv' not found."
  else
    echo "Importing accounts."
    http --check-status -b -f POST "$api_url/users/accounts" tsv@accounts.tsv
  fi
else
  echo "Skipping accounts import."
fi

read -r -p "Import contest metadata? [Y/n] " response
response=${response,,}
if [[ $response =~ ^(yes|y| ) ]] || [[ -z $response ]]; then
  if [ ! -r contest.yaml ] || [ ! -r problemset.yaml ]; then
    echo "'contest.yaml' or 'problemset.yaml' not found."
  else
    echo "Importing contest."
    cat contest.yaml problemset.yaml > combined.yaml
    cid=$(http --check-status -b -f POST "$api_url/contests" yaml@combined.yaml | sed -e 's|^"||' -e 's|"$||')
    echo "  -> cid=$cid"
    rm combined.yaml
  fi
else
  echo "Skipping contest import."
fi

read -r -p "Import problems? [Y/n] " response
response=${response,,}
if [[ $response =~ ^(yes|y| ) ]] || [[ -z $response ]]; then
  set +e
  http --check-status --pretty=format "$api_url/user" | grep -q "\"team_id\": null"
  if [ $? -eq 0 ]; then
    read -r -p "No team associated with your account. Jury submissions won't be imported. Really continue? [y/N] " response
    response=${response,,}
    if [[ ! $response =~ ^(yes|y| ) ]]; then
      exit -1 
    fi
  fi
  set -e

  if [ ! -r problemset.yaml ]; then
    echo "'problemset.yaml' not found."
  else
    echo "Importing problems."
    if [ -z "${cid+x}" ]; then
      read -r -p "Please specify the contest id: " cid
    fi
    for prob in $(cat problemset.yaml  | grep "short-name: " | sed -e 's|^ *short-name: ||'); do
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
      probid=$(http --check-status --pretty=format "$api_url/contests/$cid/problems" | grep -A1 "\"externalid\": \"$prob\"" | grep "\"id\": " | sed -e 's|.*"id": "||' -e 's|",$||')
      read -r -p "Ready to import problem '$prob' to probid=$probid. Continue? [Y/n] " response
      response=${response,,}
      if [[ $response =~ ^(yes|y| ) ]] || [[ -z $response ]]; then
        http --check-status -f POST "$api_url/contests/$cid/problems" zip[]@"${prob}.zip" problem="$probid"
      fi
    done
  fi
else
  echo "Skipping contest import."
fi
