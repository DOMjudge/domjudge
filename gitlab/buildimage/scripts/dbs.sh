#!/bin/sh -eu

echo "show databases" | mysql -hmariadb -udomjudge -pdomjudge
echo "show databases" | mysql -hmysql -udomjudge -pdomjudge
