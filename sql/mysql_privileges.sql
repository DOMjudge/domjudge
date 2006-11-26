-- Privileges for the DOMjudge MySQL tables.
--
-- You can pipe this file into the 'mysql' command to set these
-- permissions. Database should be set externally (e.g. to
-- 'domjudge'). 
--
-- $Id$


-- Juryaccount can do anything to the data (but not modify the structure)
GRANT SELECT, INSERT, UPDATE, DELETE ON * TO domjudge_jury;

-- Team/public privileges on tables
GRANT SELECT ON contest           TO domjudge_public;
GRANT SELECT ON scoreboard_jury   TO domjudge_public;
GRANT SELECT ON scoreboard_public TO domjudge_public;
GRANT SELECT ON team              TO domjudge_public;
GRANT SELECT ON team_category     TO domjudge_public;
GRANT SELECT ON team_affiliation  TO domjudge_public;

GRANT SELECT ON contest           TO domjudge_team;
GRANT SELECT ON clarification     TO domjudge_team;
GRANT SELECT ON judging           TO domjudge_team;
GRANT SELECT ON scoreboard_jury   TO domjudge_team;
GRANT SELECT ON scoreboard_public TO domjudge_team;
GRANT SELECT ON submission        TO domjudge_team;
GRANT SELECT ON team              TO domjudge_team;
GRANT SELECT ON team_category     TO domjudge_team;
GRANT SELECT ON team_affiliation  TO domjudge_team;
GRANT INSERT ON clarification     TO domjudge_team;

-- Team/public privileges on specific rows
GRANT SELECT (judgingid, submitid, result, valid)      ON judging    TO domjudge_public;
GRANT SELECT (probid, name, cid, allow_submit, color)  ON problem    TO domjudge_public;
GRANT SELECT (submitid, cid, probid, team, submittime) ON submission TO domjudge_public;

GRANT SELECT (langid, name, extension, allow_submit)   ON language   TO domjudge_team;
GRANT SELECT (probid, name, cid, allow_submit, color)  ON problem    TO domjudge_team;

GRANT UPDATE (ipaddress, teampage_first_visited)       ON team       TO domjudge_team;
