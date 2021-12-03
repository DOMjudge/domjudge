#!/bin/sh

export PS4='(${0}:${LINENO}): - [$?] $ '

set -eux

# Start the MariaDB container to connect to
# We install first against this one, and later against the mysql server
docker network create sqlnetwork
docker run -d --net=sqlnetwork --name mariadb -e MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD}" mariadb:latest
docker run -d --net=sqlnetwork --name mysql -e MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD}" mysql:latest --default-authentication-plugin=mysql_native_password
# Do the actual building of the domserver
cd ..
docker build -t "$CI_REGISTRY/mvasseur/domjudge/domserver:$CI_COMMIT_SHA" --build-arg DJ_ETC=/opt/domjudge/domserver/etc --build-arg DJ_SRC=/tmp/domjudge -f domjudge/gitlab/buildimage/Dockerfile .
# Install SQL databases
docker run --net=sqlnetwork --name domserver -e MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD}" "$CI_REGISTRY/mvasseur/domjudge/domserver:$CI_COMMIT_SHA" /scripts/configure_sql.sh
# Store the images for the next jobs
docker ps -a
docker run --net=sqlnetwork "$CI_REGISTRY/mvasseur/domjudge/domserver:$CI_COMMIT_SHA" /scripts/dbs.sh
docker login -u "$CI_REGISTRY_USER" -p "$CI_REGISTRY_PASSWORD" "$CI_REGISTRY"
# The databases have the correct value and can be stored
for SQL in mariadb mysql; do
    docker commit "$SQL" "$CI_REGISTRY/mvasseur/domjudge/$SQL:$CI_COMMIT_SHA"
done
for CONTAINER in mariadb mysql; do
    URL=$CI_REGISTRY/mvasseur/domjudge/$CONTAINER:$CI_COMMIT_SHA
    echo "$URL"
    docker tag "$URL" "$URL"
    docker push "$URL"
done
docker run -d --net=sqlnetwork --name mariadbcommited -e MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD}" "$CI_REGISTRY/mvasseur/domjudge/$CONTAINER:$CI_COMMIT_SHA"
docker run --net=sqlnetwork "$CI_REGISTRY/mvasseur/domjudge/domserver:$CI_COMMIT_SHA" /scripts/dbs2.sh
#- >
#  for CONTAINER in mariadb mysql domserver; do
#    URL=$CI_REGISTRY/mvasseur/domjudge/$CONTAINER:$CI_COMMIT_SHA
#    echo $URL
#    docker push $URL
#  done
