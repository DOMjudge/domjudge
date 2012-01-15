-- Privileges for the DOMjudge MySQL tables.
--
-- You can pipe this file into the 'mysql' command to set these
-- permissions, but preferably use 'dj-setup-database'. Database
-- should be set externally (e.g. to 'domjudge').


-- Juryaccount can do anything to the data (but not modify the structure)
GRANT SELECT, INSERT, UPDATE, DELETE ON * TO `domjudge_jury`;

-- Team/public/plugin read privileges on tables
GRANT SELECT ON configuration     TO `domjudge_public`, `domjudge_plugin`, `domjudge_team`;
GRANT SELECT ON contest           TO `domjudge_public`, `domjudge_plugin`, `domjudge_team`;
GRANT SELECT ON scoreboard_jury   TO `domjudge_public`, `domjudge_plugin`, `domjudge_team`;
GRANT SELECT ON scoreboard_public TO `domjudge_public`, `domjudge_plugin`, `domjudge_team`;
GRANT SELECT ON team              TO `domjudge_public`, `domjudge_plugin`, `domjudge_team`;
GRANT SELECT ON team_category     TO `domjudge_public`, `domjudge_plugin`, `domjudge_team`;
GRANT SELECT ON team_affiliation  TO `domjudge_public`, `domjudge_plugin`, `domjudge_team`;

GRANT SELECT ON event             TO `domjudge_plugin`;
GRANT SELECT ON clarification     TO `domjudge_plugin`, `domjudge_team`;
GRANT SELECT ON judging           TO `domjudge_team`;
GRANT SELECT ON submission        TO `domjudge_team`;
GRANT SELECT ON team_unread       TO `domjudge_team`;

-- Team/public/plugin read privileges on specific rows
GRANT SELECT (judgingid, submitid, result, valid)        ON judging    TO `domjudge_public`, `domjudge_plugin`;
GRANT SELECT (probid, name, cid, allow_submit, color)    ON problem    TO `domjudge_public`, `domjudge_plugin`;
GRANT SELECT (submitid, cid, langid, probid, teamid,
              submittime, valid)                         ON submission TO `domjudge_public`, `domjudge_plugin`;

GRANT SELECT (langid, name, allow_submit)                ON language   TO `domjudge_team`, `domjudge_plugin`;
GRANT SELECT (probid, name, cid, allow_submit, color)    ON problem    TO `domjudge_team`;

-- Team write privileges
GRANT INSERT ON clarification     TO `domjudge_team`;
GRANT DELETE ON team_unread       TO `domjudge_team`;

GRANT INSERT (cid, teamid, probid, langid, submittime, sourcecode) ON submission TO `domjudge_team`;
GRANT INSERT (cid, teamid, probid, langid, submitid, description,
              eventtime)                                           ON event      TO `domjudge_team`;
GRANT UPDATE (authtoken, hostname, teampage_first_visited)         ON team       TO `domjudge_team`;

GRANT INSERT ON auditlog TO `domjudge_team`;
-- Make sure MySQL picks up all changes
FLUSH PRIVILEGES;
