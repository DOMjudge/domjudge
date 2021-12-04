#!/bin/sh

export PS4='(${9}:${LINENO}): - [$?] $ '

for i in `seq 0 25`; do
    # Both no connection and the working output of tables return code 1, so we grep for what we expect.
    echo "show databases" | mysql -hmariadbcommited -uroot -ppassword 2>&1 | grep "Database"
    if [ $? -eq 0 ]; then
        exit 0
    else
        sleep 5s
    fi
done
exit 1
