-- Cleaning script for the DOMjudge MySQL tables.
--
-- You can pipe this file into the 'mysql' command to clean the
-- domjudge database and all privileges associated with it. Database
-- should be set externally (e.g. to 'domjudge'). USE WITH CARE!!!
--
-- This script does not remove the database and users, see mysql_remove.sql.
--
-- $Id$


-- Revoke privileges to domjudge database from domjudge users:
REVOKE ALL PRIVILEGES, GRANT OPTION FROM domjudge_jury, domjudge_team, domjudge_public;

-- Drop all tables in domjudge database (needs fix to select tables by *):
DROP TABLE IF EXISTS clarification;
DROP TABLE IF EXISTS contest;
DROP TABLE IF EXISTS event;
DROP TABLE IF EXISTS judgehost;
DROP TABLE IF EXISTS judging;
DROP TABLE IF EXISTS language;
DROP TABLE IF EXISTS problem;
DROP TABLE IF EXISTS scoreboard_jury;
DROP TABLE IF EXISTS scoreboard_public;
DROP TABLE IF EXISTS submission;
DROP TABLE IF EXISTS team;
DROP TABLE IF EXISTS team_affiliation;
DROP TABLE IF EXISTS team_category;
DROP TABLE IF EXISTS team_unread;

-- Also drop all old tables for correct upgrading:
DROP TABLE IF EXISTS category;
DROP TABLE IF EXISTS judger;
