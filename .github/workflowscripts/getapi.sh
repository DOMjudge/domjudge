#!/bin/sh

set -eux 

# Ignore the CLICS API strict mode
sudo sed -i "s/'strict'/'ignore-clics'/g" /opt/domjudge/domserver/webapp/src/Controller/API/AbstractRestController.php

curl --cacert /tmp/server.crt https://localhost/domjudge/api/doc.json > ./openapi.json
python3 -m json.tool < ./openapi.json
