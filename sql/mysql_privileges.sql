# Privileges for the DOMjudge tables.
# This assumes databasename 'domjudge'
# $Id$

# Add users and passwords
# Change these default passwords, and change them in etc/passwords.php too.
INSERT INTO user VALUES ('localhost','domjudge_jury',PASSWORD('jury'),'N','N','N','N','N','N','N','N','N','N','N','N','N','N');
INSERT INTO user VALUES ('localhost','domjudge_team',PASSWORD('team'),'N','N','N','N','N','N','N','N','N','N','N','N','N','N');
INSERT INTO user VALUES ('localhost','domjudge_public',PASSWORD('public'),'N','N','N','N','N','N','N','N','N','N','N','N','N','N');

# Juryaccount can do anything to the database
INSERT INTO db VALUES ('localhost','domjudge','domjudge_jury','Y','Y','Y','Y','Y','Y','N','N','Y','Y');

# Other privileges
INSERT INTO columns_priv VALUES ('localhost','domjudge','domjudge_team','language','name',NOW(),'Select');
INSERT INTO columns_priv VALUES ('localhost','domjudge','domjudge_team','language','langid',NOW(),'Select');
INSERT INTO columns_priv VALUES ('localhost','domjudge','domjudge_team','problem','probid',NOW(),'Select');
INSERT INTO columns_priv VALUES ('localhost','domjudge','domjudge_team','problem','cid',NOW(),'Select');
INSERT INTO columns_priv VALUES ('localhost','domjudge','domjudge_team','problem','name',NOW(),'Select');
INSERT INTO columns_priv VALUES ('localhost','domjudge','domjudge_team','problem','allow_submit',NOW(),'Select');
INSERT INTO columns_priv VALUES ('localhost','domjudge','domjudge_public','judging','judgingid',NOW(),'Select');
INSERT INTO columns_priv VALUES ('localhost','domjudge','domjudge_public','judging','submitid',NOW(),'Select');
INSERT INTO columns_priv VALUES ('localhost','domjudge','domjudge_public','judging','result',NOW(),'Select');
INSERT INTO columns_priv VALUES ('localhost','domjudge','domjudge_public','judging','valid',NOW(),'Select');
INSERT INTO columns_priv VALUES ('localhost','domjudge','domjudge_public','problem','probid',NOW(),'Select');
INSERT INTO columns_priv VALUES ('localhost','domjudge','domjudge_public','problem','cid',NOW(),'Select');
INSERT INTO columns_priv VALUES ('localhost','domjudge','domjudge_public','problem','name',NOW(),'Select');
INSERT INTO columns_priv VALUES ('localhost','domjudge','domjudge_public','problem','allow_submit',NOW(),'Select');
INSERT INTO columns_priv VALUES ('localhost','domjudge','domjudge_public','submission','team',NOW(),'Select');
INSERT INTO columns_priv VALUES ('localhost','domjudge','domjudge_public','submission','submittime',NOW(),'Select');
INSERT INTO columns_priv VALUES ('localhost','domjudge','domjudge_public','submission','submitid',NOW(),'Select');
INSERT INTO columns_priv VALUES ('localhost','domjudge','domjudge_public','submission','probid',NOW(),'Select');
INSERT INTO columns_priv VALUES ('localhost','domjudge','domjudge_public','submission','cid',NOW(),'Select');

INSERT INTO tables_priv VALUES ('localhost','domjudge','domjudge_team','judging','domjudge@localhost',NOW(),'Select','');
INSERT INTO tables_priv VALUES ('localhost','domjudge','domjudge_team','submission','domjudge@localhost',NOW(),'Select','');
INSERT INTO tables_priv VALUES ('localhost','domjudge','domjudge_team','contest','domjudge@localhost',NOW(),'Select','');
INSERT INTO tables_priv VALUES ('localhost','domjudge','domjudge_team','team','domjudge@localhost',NOW(),'Select','');
INSERT INTO tables_priv VALUES ('localhost','domjudge','domjudge_team','problem','domjudge@localhost',NOW(),'','Select');
INSERT INTO tables_priv VALUES ('localhost','domjudge','domjudge_team','language','domjudge@localhost',NOW(),'','Select');
INSERT INTO tables_priv VALUES ('localhost','domjudge','domjudge_team','category','domjudge@localhost',NOW(),'Select','');
INSERT INTO tables_priv VALUES ('localhost','domjudge','domjudge_team','clarification','domjudge@localhost',NOW(),'Select,Insert','');
INSERT INTO tables_priv VALUES ('localhost','domjudge','domjudge_team','scoreboard_public','domjudge@localhost',NOW(),'Select','');
INSERT INTO tables_priv VALUES ('localhost','domjudge','domjudge_public','category','domjudge@localhost',NOW(),'Select','');
INSERT INTO tables_priv VALUES ('localhost','domjudge','domjudge_public','contest','domjudge@localhost',NOW(),'Select','');
INSERT INTO tables_priv VALUES ('localhost','domjudge','domjudge_public','judging','domjudge@localhost',NOW(),'','Select');
INSERT INTO tables_priv VALUES ('localhost','domjudge','domjudge_public','problem','domjudge@localhost',NOW(),'','Select');
INSERT INTO tables_priv VALUES ('localhost','domjudge','domjudge_public','submission','domjudge@localhost',NOW(),'','Select');
INSERT INTO tables_priv VALUES ('localhost','domjudge','domjudge_public','team','domjudge@localhost',NOW(),'Select','');
INSERT INTO tables_priv VALUES ('localhost','domjudge','domjudge_public','scoreboard_public','domjudge@localhost',NOW(),'Select','');

FLUSH PRIVILEGES;
