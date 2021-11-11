#!/bin/sh

set -eux 

curl --cacert /tmp/server.crt https://localhost/domjudge/api/doc.json > ./openapi.json
python3 -m json.tool < ./openapi.json
