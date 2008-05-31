-- Create script for the DOMjudge MySQL tables.
--
-- This script is meant to be run from 'make install' to create the
-- DOMjudge database and users.
--
-- THIS FILE SHOULD ALWAYS BE NON-READABLE!
-- (because of database-login usernames/passwords)
--
-- $Id$

-- Create the domjudge database:
CREATE DATABASE DOMJUDGE_DBNAME CHARACTER SET utf8 COLLATE utf8_unicode_ci;

-- Add users and passwords
-- These passwords are initialised automatically when running
-- 'make install' in the SYSTEM_ROOT and removed when running
-- 'make distclean'.
-- NOTE: by default, access is allowed from ALL hosts, make sure you
-- restrict this appropriately (or choose strong enough passwords).
USE mysql;
REPLACE INTO user (Host, User, Password) VALUES ('%','domjudge_jury'  ,PASSWORD('DOMJUDGE_JURY_PASSWD'));
REPLACE INTO user (Host, User, Password) VALUES ('%','domjudge_team'  ,PASSWORD('DOMJUDGE_TEAM_PASSWD'));
REPLACE INTO user (Host, User, Password) VALUES ('%','domjudge_public',PASSWORD('DOMJUDGE_PUBLIC_PASSWD'));
REPLACE INTO user (Host, User, Password) VALUES ('%','domjudge_plugin',PASSWORD('DOMJUDGE_PLUGIN_PASSWD'));
FLUSH PRIVILEGES;
