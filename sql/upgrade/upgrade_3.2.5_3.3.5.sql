-- This script upgrades table structure, data, and privileges
-- from/to the exact version numbers specified in the filename.

--
-- First execute a check whether this upgrade should apply. The check
-- below should fail if this upgrade has already been applied, but
-- keep everything unchanged if not.
--

-- @UPGRADE-CHECK@
ALTER TABLE `team` ADD  COLUMN `judging_last_started` datetime default NULL;
ALTER TABLE `team` DROP COLUMN `judging_last_started`;

--
-- Create additional structures
--

ALTER TABLE `configuration`
  DROP PRIMARY KEY,
  ADD COLUMN `configid` int(4) NOT NULL AUTO_INCREMENT COMMENT 'Configuration ID' FIRST,
  ADD COLUMN `type` varchar(25) default NULL COMMENT 'Type of the value (metatype for use in the webinterface)' AFTER `value`,
  ADD COLUMN `description` varchar(255) default NULL COMMENT 'Description for in the webinterface' AFTER `type`,
  ADD PRIMARY KEY (`configid`),
  ADD KEY `name` (`name`);

ALTER TABLE `contest`
  MODIFY COLUMN `activatetime_string` varchar(20) NOT NULL COMMENT 'Authoritative absolute or relative string representation of activatetime',
  MODIFY COLUMN `freezetime_string` varchar(20) default NULL COMMENT 'Authoritative absolute or relative string representation of freezetime',
  MODIFY COLUMN `endtime_string` varchar(20) NOT NULL COMMENT 'Authoritative absolute or relative string representation of endtime',
  MODIFY COLUMN `unfreezetime_string` varchar(20) default NULL COMMENT 'Authoritative absolute or relative string representation of unfreezetrime';

ALTER TABLE `judging`
  ADD COLUMN `verify_comment` varchar(255) DEFAULT NULL COMMENT 'Optional additional information provided by the verifier' AFTER `jury_member`;

ALTER TABLE `team`
  MODIFY COLUMN `name` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL COMMENT 'Team name',
  ADD COLUMN `judging_last_started` datetime default NULL COMMENT 'Start time of last judging for priorization' AFTER `comments`,
  ADD COLUMN `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Whether the team is visible and operational' AFTER `authtoken`;

ALTER TABLE `team_affiliation`
  MODIFY COLUMN `country` char(3) default NULL COMMENT 'ISO 3166-1 alpha-3 country code';

ALTER TABLE `scoreboard_jury`
  ADD COLUMN `pending` int(4) NOT NULL DEFAULT '0' COMMENT 'Number of submissions pending judgement' AFTER `submissions`;

ALTER TABLE `scoreboard_public`
  ADD COLUMN `pending` int(4) NOT NULL  DEFAULT '0'COMMENT 'Number of submissions pending judgement' AFTER `submissions`;

CREATE TABLE `auditlog` (
  `logid` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `logtime` datetime NOT NULL COMMENT 'Timestamp of the logentry',
  `cid` int(4) unsigned DEFAULT NULL COMMENT 'Contest ID associated to this entry',
  `user` varchar(255) DEFAULT NULL COMMENT 'User who performed this action',
  `datatype` varchar(25) DEFAULT NULL COMMENT 'Reference to DB table associated to this entry',
  `dataid` varchar(50) DEFAULT NULL COMMENT 'Identifier in reference table',
  `action` varchar(30) DEFAULT NULL COMMENT 'Description of action performed',
  `extrainfo` varchar(255) DEFAULT NULL COMMENT 'Optional additional description of the entry',
  PRIMARY KEY (`logid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Log of all actions performed';

CREATE TABLE `balloon` (
  `balloonid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `submitid` int(4) unsigned NOT NULL COMMENT 'Submission for which balloon was earned',
  `done` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Has been handed out yet?',
  PRIMARY KEY (`balloonid`),
  KEY `submitid` (`submitid`),
  CONSTRAINT `balloon_ibfk_1` FOREIGN KEY (`submitid`) REFERENCES `submission` (`submitid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Balloons to be handed out';

CREATE TABLE `submission_file` (
  `submitfileid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `submitid` int(4) unsigned NOT NULL COMMENT 'Submission this file belongs to',
  `sourcecode` longblob NOT NULL COMMENT 'Full source code',
  `filename` varchar(255) NOT NULL COMMENT 'Filename as submitted',
  `rank` int(4) unsigned NOT NULL COMMENT 'Order of the submission files, zero-indexed',
  PRIMARY KEY (`submitfileid`),
  UNIQUE KEY `filename` (`submitid`,`filename`),
  UNIQUE KEY `rank` (`submitid`,`rank`),
  KEY `submitid` (`submitid`),
  CONSTRAINT `submission_file_ibfk_1` FOREIGN KEY (`submitid`) REFERENCES `submission` (`submitid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Files associated to a submission';

-- Resize datastructures to fit "arbitrary" large data to satisfy
-- the ICPC CSS spec.
ALTER TABLE `clarification`
  MODIFY COLUMN `body` longtext NOT NULL COMMENT 'Clarification text';

ALTER TABLE `configuration`
  MODIFY `value` longtext NOT NULL COMMENT 'Content of the configuration variable';

ALTER TABLE `event`
  MODIFY COLUMN `description` longtext NOT NULL COMMENT 'Event description';

ALTER TABLE `judging`
  MODIFY COLUMN `output_compile` longblob COMMENT 'Output of the compiling the program',
  ADD COLUMN `seen` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Whether the team has seen this judging' after `output_compile`;

ALTER TABLE `judging_run`
  MODIFY COLUMN `output_run` longblob COMMENT 'Output of running the program',
  MODIFY COLUMN `output_diff` longblob COMMENT 'Diffing the program output and testcase output',
  MODIFY COLUMN `output_error` longblob COMMENT 'Standard error output of the program';

ALTER TABLE `submission`
  MODIFY COLUMN `sourcecode` longblob NOT NULL COMMENT 'Full source code',
  ADD COLUMN `origsubmitid` int(4) unsigned default NULL COMMENT 'If set, specifies original submission in case of edit/resubmit' AFTER `submitid`,
  ADD KEY `origsubmitid` (`origsubmitid`),
  ADD CONSTRAINT `submission_ibfk_6` FOREIGN KEY (`origsubmitid`) REFERENCES `submission` (`submitid`) ON DELETE SET NULL;

ALTER TABLE `team`
  MODIFY COLUMN `members` longtext COMMENT 'Team member names (freeform)',
  MODIFY COLUMN `comments` longtext COMMENT 'Comments about this team';

ALTER TABLE `team_affiliation`
  MODIFY COLUMN `comments` longtext COMMENT 'Comments';

--
-- Transfer data from old to new structure
--

INSERT INTO `submission_file` (`submitid`, `rank`, `sourcecode`, `filename`)
  SELECT `submitid`, '0', `sourcecode`, CONCAT('source.',`langid`) FROM submission;

--
-- Add/remove sample/initial contents
--

UPDATE `configuration` SET `type` = 'bool', `description` = 'Show affiliations names and icons in the scoreboard?' WHERE `name` = 'show_affiliations';

INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('show_pending', '0', 'bool', 'Show pending submissions on the scoreboard?');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('show_compile', '2', 'int', 'Show compile output in team webinterface? Choices: 0 = never, 1 = only on compilation error(s), 2 = always.');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('compile_time', '30', 'int', 'Maximum seconds available for compiling.');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('memory_limit', '524288', 'int', 'Maximum memory usage (in kB) a submission. This includes the shell which starts the compiled solution and also any interpreter like the Java VM, which takes away approx. 300MB!');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('filesize_limit', '4096', 'int', 'Maximum file size (in kB) that a submission may write. Solutions will abort when trying to write more, so this should be greater than the maximum testdata output.');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('process_limit', '15', 'int', 'Maximum number of processes that a submission is allowed to start (including shell and possibly interpreters).');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('sourcesize_limit', '256', 'int', 'Maximum source code size (in kB) of a submission. This setting should be kept in sync with that in "etc/submit-config.h.in".');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('sourcefiles_limit', '100', 'int', 'Maximum number of source files in one submission. Set to one to disable multiple file submissions.');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('verification_required', '0', 'bool', 'Is verification of judgings by jury required before publication?');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('penalty_time', '20', 'int', 'Penalty time in minutes per wrong submission (if finally solved).');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('results_prio', '{"memory-limit":99,"output-limit":99,"run-error":99,"timelimit":99,"wrong-answer":30,"presentation-error":20,"no-output":10,"correct":1}', 'array_keyval', 'Priorities of results for determining final result with multiple testcases. Higher priority is used first as final result. With equal priority, the first occurring result determines the final result.');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('results_remap', '{"presentation-error":"wrong-answer"}', 'array_keyval', 'Remap testcase result, e.g. to disable a specific result type such as ''presentation-error''.');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('lazy_eval_results', '1', 'bool', 'Lazy evaluation of results? If enabled, stops judging as soon as a highest priority result is found, otherwise always all testcases will be judged.');

UPDATE `team_affiliation` SET `country` = 'AFG' WHERE country = 'AF';
UPDATE `team_affiliation` SET `country` = 'ALB' WHERE country = 'AL';
UPDATE `team_affiliation` SET `country` = 'DZA' WHERE country = 'DZ';
UPDATE `team_affiliation` SET `country` = 'ASM' WHERE country = 'AS';
UPDATE `team_affiliation` SET `country` = 'AND' WHERE country = 'AD';
UPDATE `team_affiliation` SET `country` = 'AGO' WHERE country = 'AO';
UPDATE `team_affiliation` SET `country` = 'AIA' WHERE country = 'AI';
UPDATE `team_affiliation` SET `country` = 'ATA' WHERE country = 'AQ';
UPDATE `team_affiliation` SET `country` = 'ATG' WHERE country = 'AG';
UPDATE `team_affiliation` SET `country` = 'ARG' WHERE country = 'AR';
UPDATE `team_affiliation` SET `country` = 'ARM' WHERE country = 'AM';
UPDATE `team_affiliation` SET `country` = 'ABW' WHERE country = 'AW';
UPDATE `team_affiliation` SET `country` = 'AUS' WHERE country = 'AU';
UPDATE `team_affiliation` SET `country` = 'AUT' WHERE country = 'AT';
UPDATE `team_affiliation` SET `country` = 'AZE' WHERE country = 'AZ';
UPDATE `team_affiliation` SET `country` = 'BHS' WHERE country = 'BS';
UPDATE `team_affiliation` SET `country` = 'BHR' WHERE country = 'BH';
UPDATE `team_affiliation` SET `country` = 'BGD' WHERE country = 'BD';
UPDATE `team_affiliation` SET `country` = 'BRB' WHERE country = 'BB';
UPDATE `team_affiliation` SET `country` = 'BLR' WHERE country = 'BY';
UPDATE `team_affiliation` SET `country` = 'BEL' WHERE country = 'BE';
UPDATE `team_affiliation` SET `country` = 'BLZ' WHERE country = 'BZ';
UPDATE `team_affiliation` SET `country` = 'BEN' WHERE country = 'BJ';
UPDATE `team_affiliation` SET `country` = 'BMU' WHERE country = 'BM';
UPDATE `team_affiliation` SET `country` = 'BTN' WHERE country = 'BT';
UPDATE `team_affiliation` SET `country` = 'BOL' WHERE country = 'BO';
UPDATE `team_affiliation` SET `country` = 'BIH' WHERE country = 'BA';
UPDATE `team_affiliation` SET `country` = 'BWA' WHERE country = 'BW';
UPDATE `team_affiliation` SET `country` = 'BVT' WHERE country = 'BV';
UPDATE `team_affiliation` SET `country` = 'BRA' WHERE country = 'BR';
UPDATE `team_affiliation` SET `country` = 'IOT' WHERE country = 'IO';
UPDATE `team_affiliation` SET `country` = 'VGB' WHERE country = 'VG';
UPDATE `team_affiliation` SET `country` = 'BRN' WHERE country = 'BN';
UPDATE `team_affiliation` SET `country` = 'BGR' WHERE country = 'BG';
UPDATE `team_affiliation` SET `country` = 'BFA' WHERE country = 'BF';
UPDATE `team_affiliation` SET `country` = 'MMR' WHERE country = 'MM';
UPDATE `team_affiliation` SET `country` = 'BDI' WHERE country = 'BI';
UPDATE `team_affiliation` SET `country` = 'KHM' WHERE country = 'KH';
UPDATE `team_affiliation` SET `country` = 'CMR' WHERE country = 'CM';
UPDATE `team_affiliation` SET `country` = 'CAN' WHERE country = 'CA';
UPDATE `team_affiliation` SET `country` = 'CPV' WHERE country = 'CV';
UPDATE `team_affiliation` SET `country` = 'CYM' WHERE country = 'KY';
UPDATE `team_affiliation` SET `country` = 'CAF' WHERE country = 'CF';
UPDATE `team_affiliation` SET `country` = 'TCD' WHERE country = 'TD';
UPDATE `team_affiliation` SET `country` = 'CHL' WHERE country = 'CL';
UPDATE `team_affiliation` SET `country` = 'CHN' WHERE country = 'CN';
UPDATE `team_affiliation` SET `country` = 'CXR' WHERE country = 'CX';
UPDATE `team_affiliation` SET `country` = 'CCK' WHERE country = 'CC';
UPDATE `team_affiliation` SET `country` = 'COL' WHERE country = 'CO';
UPDATE `team_affiliation` SET `country` = 'COM' WHERE country = 'KM';
UPDATE `team_affiliation` SET `country` = 'COD' WHERE country = 'CD';
UPDATE `team_affiliation` SET `country` = 'COG' WHERE country = 'CG';
UPDATE `team_affiliation` SET `country` = 'COK' WHERE country = 'CK';
UPDATE `team_affiliation` SET `country` = 'CRI' WHERE country = 'CR';
UPDATE `team_affiliation` SET `country` = 'CIV' WHERE country = 'CI';
UPDATE `team_affiliation` SET `country` = 'HRV' WHERE country = 'HR';
UPDATE `team_affiliation` SET `country` = 'CUB' WHERE country = 'CU';
UPDATE `team_affiliation` SET `country` = 'CUW' WHERE country = 'CW';
UPDATE `team_affiliation` SET `country` = 'CYP' WHERE country = 'CY';
UPDATE `team_affiliation` SET `country` = 'CZE' WHERE country = 'CZ';
UPDATE `team_affiliation` SET `country` = 'DNK' WHERE country = 'DK';
UPDATE `team_affiliation` SET `country` = 'DJI' WHERE country = 'DJ';
UPDATE `team_affiliation` SET `country` = 'DMA' WHERE country = 'DM';
UPDATE `team_affiliation` SET `country` = 'DOM' WHERE country = 'DO';
UPDATE `team_affiliation` SET `country` = 'TLS' WHERE country = 'TL';
UPDATE `team_affiliation` SET `country` = 'ECU' WHERE country = 'EC';
UPDATE `team_affiliation` SET `country` = 'EGY' WHERE country = 'EG';
UPDATE `team_affiliation` SET `country` = 'SLV' WHERE country = 'SV';
UPDATE `team_affiliation` SET `country` = 'GNQ' WHERE country = 'GQ';
UPDATE `team_affiliation` SET `country` = 'ERI' WHERE country = 'ER';
UPDATE `team_affiliation` SET `country` = 'EST' WHERE country = 'EE';
UPDATE `team_affiliation` SET `country` = 'ETH' WHERE country = 'ET';
UPDATE `team_affiliation` SET `country` = 'FLK' WHERE country = 'FK';
UPDATE `team_affiliation` SET `country` = 'FRO' WHERE country = 'FO';
UPDATE `team_affiliation` SET `country` = 'FJI' WHERE country = 'FJ';
UPDATE `team_affiliation` SET `country` = 'FIN' WHERE country = 'FI';
UPDATE `team_affiliation` SET `country` = 'FRA' WHERE country = 'FR';
UPDATE `team_affiliation` SET `country` = 'FXX' WHERE country = 'FX';
UPDATE `team_affiliation` SET `country` = 'GUF' WHERE country = 'GF';
UPDATE `team_affiliation` SET `country` = 'PYF' WHERE country = 'PF';
UPDATE `team_affiliation` SET `country` = 'ATF' WHERE country = 'TF';
UPDATE `team_affiliation` SET `country` = 'GAB' WHERE country = 'GA';
UPDATE `team_affiliation` SET `country` = 'GMB' WHERE country = 'GM';
UPDATE `team_affiliation` SET `country` = 'PSE' WHERE country = 'PS';
UPDATE `team_affiliation` SET `country` = 'GEO' WHERE country = 'GE';
UPDATE `team_affiliation` SET `country` = 'DEU' WHERE country = 'DE';
UPDATE `team_affiliation` SET `country` = 'GHA' WHERE country = 'GH';
UPDATE `team_affiliation` SET `country` = 'GIB' WHERE country = 'GI';
UPDATE `team_affiliation` SET `country` = 'GRC' WHERE country = 'GR';
UPDATE `team_affiliation` SET `country` = 'GRL' WHERE country = 'GL';
UPDATE `team_affiliation` SET `country` = 'GRD' WHERE country = 'GD';
UPDATE `team_affiliation` SET `country` = 'GLP' WHERE country = 'GP';
UPDATE `team_affiliation` SET `country` = 'GUM' WHERE country = 'GU';
UPDATE `team_affiliation` SET `country` = 'GTM' WHERE country = 'GT';
UPDATE `team_affiliation` SET `country` = 'GGY' WHERE country = 'GG';
UPDATE `team_affiliation` SET `country` = 'GIN' WHERE country = 'GN';
UPDATE `team_affiliation` SET `country` = 'GNB' WHERE country = 'GW';
UPDATE `team_affiliation` SET `country` = 'GUY' WHERE country = 'GY';
UPDATE `team_affiliation` SET `country` = 'HTI' WHERE country = 'HT';
UPDATE `team_affiliation` SET `country` = 'HMD' WHERE country = 'HM';
UPDATE `team_affiliation` SET `country` = 'VAT' WHERE country = 'VA';
UPDATE `team_affiliation` SET `country` = 'HND' WHERE country = 'HN';
UPDATE `team_affiliation` SET `country` = 'HKG' WHERE country = 'HK';
UPDATE `team_affiliation` SET `country` = 'HUN' WHERE country = 'HU';
UPDATE `team_affiliation` SET `country` = 'ISL' WHERE country = 'IS';
UPDATE `team_affiliation` SET `country` = 'IND' WHERE country = 'IN';
UPDATE `team_affiliation` SET `country` = 'IDN' WHERE country = 'ID';
UPDATE `team_affiliation` SET `country` = 'IRN' WHERE country = 'IR';
UPDATE `team_affiliation` SET `country` = 'IRQ' WHERE country = 'IQ';
UPDATE `team_affiliation` SET `country` = 'IRL' WHERE country = 'IE';
UPDATE `team_affiliation` SET `country` = 'IMN' WHERE country = 'IM';
UPDATE `team_affiliation` SET `country` = 'ISR' WHERE country = 'IL';
UPDATE `team_affiliation` SET `country` = 'ITA' WHERE country = 'IT';
UPDATE `team_affiliation` SET `country` = 'JAM' WHERE country = 'JM';
UPDATE `team_affiliation` SET `country` = 'JPN' WHERE country = 'JP';
UPDATE `team_affiliation` SET `country` = 'JEY' WHERE country = 'JE';
UPDATE `team_affiliation` SET `country` = 'JOR' WHERE country = 'JO';
UPDATE `team_affiliation` SET `country` = 'KAZ' WHERE country = 'KZ';
UPDATE `team_affiliation` SET `country` = 'KEN' WHERE country = 'KE';
UPDATE `team_affiliation` SET `country` = 'KIR' WHERE country = 'KI';
UPDATE `team_affiliation` SET `country` = 'PRK' WHERE country = 'KP';
UPDATE `team_affiliation` SET `country` = 'KOR' WHERE country = 'KR';
UPDATE `team_affiliation` SET `country` = 'KWT' WHERE country = 'KW';
UPDATE `team_affiliation` SET `country` = 'KGZ' WHERE country = 'KG';
UPDATE `team_affiliation` SET `country` = 'LAO' WHERE country = 'LA';
UPDATE `team_affiliation` SET `country` = 'LVA' WHERE country = 'LV';
UPDATE `team_affiliation` SET `country` = 'LBN' WHERE country = 'LB';
UPDATE `team_affiliation` SET `country` = 'LSO' WHERE country = 'LS';
UPDATE `team_affiliation` SET `country` = 'LBR' WHERE country = 'LR';
UPDATE `team_affiliation` SET `country` = 'LBY' WHERE country = 'LY';
UPDATE `team_affiliation` SET `country` = 'LIE' WHERE country = 'LI';
UPDATE `team_affiliation` SET `country` = 'LTU' WHERE country = 'LT';
UPDATE `team_affiliation` SET `country` = 'LUX' WHERE country = 'LU';
UPDATE `team_affiliation` SET `country` = 'MAC' WHERE country = 'MO';
UPDATE `team_affiliation` SET `country` = 'MKD' WHERE country = 'MK';
UPDATE `team_affiliation` SET `country` = 'MDG' WHERE country = 'MG';
UPDATE `team_affiliation` SET `country` = 'MWI' WHERE country = 'MW';
UPDATE `team_affiliation` SET `country` = 'MYS' WHERE country = 'MY';
UPDATE `team_affiliation` SET `country` = 'MDV' WHERE country = 'MV';
UPDATE `team_affiliation` SET `country` = 'MLI' WHERE country = 'ML';
UPDATE `team_affiliation` SET `country` = 'MLT' WHERE country = 'MT';
UPDATE `team_affiliation` SET `country` = 'MHL' WHERE country = 'MH';
UPDATE `team_affiliation` SET `country` = 'MTQ' WHERE country = 'MQ';
UPDATE `team_affiliation` SET `country` = 'MRT' WHERE country = 'MR';
UPDATE `team_affiliation` SET `country` = 'MUS' WHERE country = 'MU';
UPDATE `team_affiliation` SET `country` = 'MYT' WHERE country = 'YT';
UPDATE `team_affiliation` SET `country` = 'MEX' WHERE country = 'MX';
UPDATE `team_affiliation` SET `country` = 'FSM' WHERE country = 'FM';
UPDATE `team_affiliation` SET `country` = 'MDA' WHERE country = 'MD';
UPDATE `team_affiliation` SET `country` = 'MCO' WHERE country = 'MC';
UPDATE `team_affiliation` SET `country` = 'MNG' WHERE country = 'MN';
UPDATE `team_affiliation` SET `country` = 'MNE' WHERE country = 'ME';
UPDATE `team_affiliation` SET `country` = 'MSR' WHERE country = 'MS';
UPDATE `team_affiliation` SET `country` = 'MAR' WHERE country = 'MA';
UPDATE `team_affiliation` SET `country` = 'MOZ' WHERE country = 'MZ';
UPDATE `team_affiliation` SET `country` = 'NAM' WHERE country = 'NA';
UPDATE `team_affiliation` SET `country` = 'NRU' WHERE country = 'NR';
UPDATE `team_affiliation` SET `country` = 'NPL' WHERE country = 'NP';
UPDATE `team_affiliation` SET `country` = 'NLD' WHERE country = 'NL';
UPDATE `team_affiliation` SET `country` = 'ANT' WHERE country = 'AN';
UPDATE `team_affiliation` SET `country` = 'NCL' WHERE country = 'NC';
UPDATE `team_affiliation` SET `country` = 'NZL' WHERE country = 'NZ';
UPDATE `team_affiliation` SET `country` = 'NIC' WHERE country = 'NI';
UPDATE `team_affiliation` SET `country` = 'NER' WHERE country = 'NE';
UPDATE `team_affiliation` SET `country` = 'NGA' WHERE country = 'NG';
UPDATE `team_affiliation` SET `country` = 'NIU' WHERE country = 'NU';
UPDATE `team_affiliation` SET `country` = 'NFK' WHERE country = 'NF';
UPDATE `team_affiliation` SET `country` = 'MNP' WHERE country = 'MP';
UPDATE `team_affiliation` SET `country` = 'NOR' WHERE country = 'NO';
UPDATE `team_affiliation` SET `country` = 'OMN' WHERE country = 'OM';
UPDATE `team_affiliation` SET `country` = 'PAK' WHERE country = 'PK';
UPDATE `team_affiliation` SET `country` = 'PLW' WHERE country = 'PW';
UPDATE `team_affiliation` SET `country` = 'PAN' WHERE country = 'PA';
UPDATE `team_affiliation` SET `country` = 'PNG' WHERE country = 'PG';
UPDATE `team_affiliation` SET `country` = 'PRY' WHERE country = 'PY';
UPDATE `team_affiliation` SET `country` = 'PER' WHERE country = 'PE';
UPDATE `team_affiliation` SET `country` = 'PHL' WHERE country = 'PH';
UPDATE `team_affiliation` SET `country` = 'PCN' WHERE country = 'PN';
UPDATE `team_affiliation` SET `country` = 'POL' WHERE country = 'PL';
UPDATE `team_affiliation` SET `country` = 'PRT' WHERE country = 'PT';
UPDATE `team_affiliation` SET `country` = 'PRI' WHERE country = 'PR';
UPDATE `team_affiliation` SET `country` = 'QAT' WHERE country = 'QA';
UPDATE `team_affiliation` SET `country` = 'REU' WHERE country = 'RE';
UPDATE `team_affiliation` SET `country` = 'ROU' WHERE country = 'RO';
UPDATE `team_affiliation` SET `country` = 'RUS' WHERE country = 'RU';
UPDATE `team_affiliation` SET `country` = 'RWA' WHERE country = 'RW';
UPDATE `team_affiliation` SET `country` = 'BLM' WHERE country = 'BL';
UPDATE `team_affiliation` SET `country` = 'SHN' WHERE country = 'SH';
UPDATE `team_affiliation` SET `country` = 'KNA' WHERE country = 'KN';
UPDATE `team_affiliation` SET `country` = 'LCA' WHERE country = 'LC';
UPDATE `team_affiliation` SET `country` = 'MAF' WHERE country = 'MF';
UPDATE `team_affiliation` SET `country` = 'SPM' WHERE country = 'PM';
UPDATE `team_affiliation` SET `country` = 'VCT' WHERE country = 'VC';
UPDATE `team_affiliation` SET `country` = 'WSM' WHERE country = 'WS';
UPDATE `team_affiliation` SET `country` = 'SMR' WHERE country = 'SM';
UPDATE `team_affiliation` SET `country` = 'STP' WHERE country = 'ST';
UPDATE `team_affiliation` SET `country` = 'SAU' WHERE country = 'SA';
UPDATE `team_affiliation` SET `country` = 'SEN' WHERE country = 'SN';
UPDATE `team_affiliation` SET `country` = 'SRB' WHERE country = 'RS';
UPDATE `team_affiliation` SET `country` = 'SYC' WHERE country = 'SC';
UPDATE `team_affiliation` SET `country` = 'SLE' WHERE country = 'SL';
UPDATE `team_affiliation` SET `country` = 'SGP' WHERE country = 'SG';
UPDATE `team_affiliation` SET `country` = 'SXM' WHERE country = 'SX';
UPDATE `team_affiliation` SET `country` = 'SVK' WHERE country = 'SK';
UPDATE `team_affiliation` SET `country` = 'SVN' WHERE country = 'SI';
UPDATE `team_affiliation` SET `country` = 'SLB' WHERE country = 'SB';
UPDATE `team_affiliation` SET `country` = 'SOM' WHERE country = 'SO';
UPDATE `team_affiliation` SET `country` = 'ZAF' WHERE country = 'ZA';
UPDATE `team_affiliation` SET `country` = 'SGS' WHERE country = 'GS';
UPDATE `team_affiliation` SET `country` = 'SSD' WHERE country = 'SS';
UPDATE `team_affiliation` SET `country` = 'ESP' WHERE country = 'ES';
UPDATE `team_affiliation` SET `country` = 'LKA' WHERE country = 'LK';
UPDATE `team_affiliation` SET `country` = 'SDN' WHERE country = 'SD';
UPDATE `team_affiliation` SET `country` = 'SUR' WHERE country = 'SR';
UPDATE `team_affiliation` SET `country` = 'SJM' WHERE country = 'SJ';
UPDATE `team_affiliation` SET `country` = 'SWZ' WHERE country = 'SZ';
UPDATE `team_affiliation` SET `country` = 'SWE' WHERE country = 'SE';
UPDATE `team_affiliation` SET `country` = 'CHE' WHERE country = 'CH';
UPDATE `team_affiliation` SET `country` = 'SYR' WHERE country = 'SY';
UPDATE `team_affiliation` SET `country` = 'TWN' WHERE country = 'TW';
UPDATE `team_affiliation` SET `country` = 'TJK' WHERE country = 'TJ';
UPDATE `team_affiliation` SET `country` = 'TZA' WHERE country = 'TZ';
UPDATE `team_affiliation` SET `country` = 'THA' WHERE country = 'TH';
UPDATE `team_affiliation` SET `country` = 'TGO' WHERE country = 'TG';
UPDATE `team_affiliation` SET `country` = 'TKL' WHERE country = 'TK';
UPDATE `team_affiliation` SET `country` = 'TON' WHERE country = 'TO';
UPDATE `team_affiliation` SET `country` = 'TTO' WHERE country = 'TT';
UPDATE `team_affiliation` SET `country` = 'TUN' WHERE country = 'TN';
UPDATE `team_affiliation` SET `country` = 'TUR' WHERE country = 'TR';
UPDATE `team_affiliation` SET `country` = 'TKM' WHERE country = 'TM';
UPDATE `team_affiliation` SET `country` = 'TCA' WHERE country = 'TC';
UPDATE `team_affiliation` SET `country` = 'TUV' WHERE country = 'TV';
UPDATE `team_affiliation` SET `country` = 'UGA' WHERE country = 'UG';
UPDATE `team_affiliation` SET `country` = 'UKR' WHERE country = 'UA';
UPDATE `team_affiliation` SET `country` = 'ARE' WHERE country = 'AE';
UPDATE `team_affiliation` SET `country` = 'GBR' WHERE country = 'GB';
UPDATE `team_affiliation` SET `country` = 'USA' WHERE country = 'US';
UPDATE `team_affiliation` SET `country` = 'UMI' WHERE country = 'UM';
UPDATE `team_affiliation` SET `country` = 'URY' WHERE country = 'UY';
UPDATE `team_affiliation` SET `country` = 'UZB' WHERE country = 'UZ';
UPDATE `team_affiliation` SET `country` = 'VUT' WHERE country = 'VU';
UPDATE `team_affiliation` SET `country` = 'VEN' WHERE country = 'VE';
UPDATE `team_affiliation` SET `country` = 'VNM' WHERE country = 'VN';
UPDATE `team_affiliation` SET `country` = 'VIR' WHERE country = 'VI';
UPDATE `team_affiliation` SET `country` = 'WLF' WHERE country = 'WF';
UPDATE `team_affiliation` SET `country` = 'PSE' WHERE country = 'PS';
UPDATE `team_affiliation` SET `country` = 'ESH' WHERE country = 'EH';
UPDATE `team_affiliation` SET `country` = 'YEM' WHERE country = 'YE';
UPDATE `team_affiliation` SET `country` = 'ZMB' WHERE country = 'ZM';
UPDATE `team_affiliation` SET `country` = 'ZWE' WHERE country = 'ZW';

--
-- Finally remove obsolete structures after moving data
--

ALTER TABLE `scoreboard_jury`
  DROP COLUMN balloon;

ALTER TABLE `submission`
  DROP COLUMN sourcecode;
