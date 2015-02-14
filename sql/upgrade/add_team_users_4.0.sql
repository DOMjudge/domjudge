-- This script adds users for existing teams when upgrading to
-- DOMjudge version 4.0.X. This script is not automatically used
-- during upgrade; instead uncomment the inclusion of this script in
-- upgrade_3.4.0_4.0.0.sql. Note that it cannot be run separately
-- after upgrading to 4.0.X as it depends on the team.login column.

-- Use team.authtoken if it looks like a MD5 hash. Note that the
-- username has to be identical to the original team.login.
INSERT INTO `user` (`username`, `name`, `password`, `teamid`)
  SELECT `login`, `name`, IF(`authtoken` REGEXP '^[a-z0-9]{32}$',`authtoken`,NULL), `teamid`
  FROM `team`;

INSERT INTO `userrole` (`userid`, `roleid`)
  SELECT `user`.`userid`, `role`.`roleid`
  FROM `user` LEFT JOIN `role` ON (`role`.`role` = 'team')
  WHERE `user`.`teamid` IS NOT NULL;
