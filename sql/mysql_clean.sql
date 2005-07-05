# Cleaning script for the DOMjudge MySQL tables.
# This assumes database name 'domjudge'
#
# You can pipe this file into the 'mysql' command to remove the
# domjudge database and all privileges associated with it.
# USE WITH CARE!!!
#
# $Id$

# Remove the whole 'domjudge' database:
DROP DATABASE IF EXISTS domjudge;

USE mysql;

# Remove domjudge users:
DELETE FROM `user` WHERE `User` = 'domjudge_jury';
DELETE FROM `user` WHERE `User` = 'domjudge_team';
DELETE FROM `user` WHERE `User` = 'domjudge_public';

# Remove privileges:
DELETE FROM `db`           WHERE `Db` = 'domjudge';
DELETE FROM `columns_priv` WHERE `Db` = 'domjudge';
DELETE FROM `tables_priv`  WHERE `Db` = 'domjudge';
