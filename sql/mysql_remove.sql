-- Remove script for the DOMjudge MySQL tables.
--
-- This script is meant to be run from 'make install' to remove the
-- domjudge database and the users associated with it. USE WITH CARE!!!
--
-- $Id: mysql_remove.sql 1170 2006-11-26 14:52:33Z eldering $


-- Remove the whole 'domjudge' database:
DROP DATABASE IF EXISTS DOMJUDGE_DBNAME;

-- Remove domjudge users:
DROP USER domjudge_jury, domjudge_team, domjudge_public;
