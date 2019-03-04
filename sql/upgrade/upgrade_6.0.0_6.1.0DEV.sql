-- This script upgrades table structure, data, and privileges
-- from/to the exact version numbers specified in the filename.

--
-- First execute a check whether this upgrade should apply. The check
-- below should fail if this upgrade has already been applied, but
-- keep everything unchanged if not.
--

-- @UPGRADE-CHECK@
ALTER TABLE `configuration` ADD  COLUMN `category` varchar(32);
ALTER TABLE `configuration` DROP COLUMN `category`;

--
-- Create additional structures
--

ALTER TABLE `configuration`
  ADD COLUMN `category` varchar(32) NOT NULL DEFAULT 'Uncategorized' COMMENT 'Option category of the configuration variable' AFTER `public`;
ALTER TABLE `problem`
  ADD COLUMN `combined_run_compare` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Use the exit code of the run script to compute the verdict' AFTER `special_compare_args`;


--
-- Transfer data from old to new structure
--

UPDATE `configuration` SET `category` = 'Scoring' WHERE `name` IN (
  'verification_required', 'compile_penalty', 'penalty_time',
  'results_prio', 'results_remap', 'score_in_seconds'
);
UPDATE `configuration` SET `category` = 'Judging' WHERE `name` IN (
  'memory_limit', 'output_limit', 'process_limit', 'sourcesize_limit',
  'sourcefiles_limit', 'script_timelimit', 'script_memory_limit',
  'script_filesize_limit', 'timelimit_overshoot', 'output_storage_limit',
  'output_display_limit', 'lazy_eval_results', 'judgehost_warning',
  'judgehost_critical', 'diskspace_error', 'default_compare', 'default_run'
);
UPDATE `configuration` SET `category` = 'Clarification' WHERE `name` IN (
  'clar_categories', 'clar_answers', 'clar_queues', 'clar_default_problem_queue'
);
UPDATE `configuration` SET `category` = 'Display' WHERE `name` IN (
  'show_pending', 'show_flags', 'show_affiliations', 'show_affiliation_logos',
  'show_teams_submissions', 'show_compile', 'show_sample_output',
  'show_balloons_postfreeze', 'show_relative_time', 'time_format',
  'thumbnail_size', 'show_limits_on_team_page'
);
UPDATE `configuration` SET `category` = 'Misc' WHERE `name` IN (
  'enable_printing', 'allow_registration', 'allow_openid_auth',
  'openid_autocreate_team', 'openid_provider', 'openid_clientid',
  'openid_clientsecret'
);

--
-- Add/remove sample/initial contents
--

INSERT INTO `configuration` (`name`, `value`, `type`, `public`, `category`, `description`) VALUES
('data_source', '0', 'int', '0', 'Misc', 'Source of data. Choices: 0 = all local, 1 = configuration data external, 2 = configuration and live data external'),
('update_judging_seconds', '0', 'int', '0', 'Judging', 'Post updates to a judging every X seconds. Set to 0 to update after each judging_run.');

INSERT INTO `role` (`role`, `description`) VALUES
('api_reader', 'API reader'),
('api_writer', 'API writer'),
('api_source_reader', 'Source code reader');

DELETE FROM `role` WHERE `role` IN ('print', 'event_reader', 'full_event_reader');

UPDATE `language` SET `externalid` = 'ada' WHERE langid = 'adb';
UPDATE `language` SET `externalid` = 'ruby' WHERE langid = 'rb';
UPDATE `language` SET `externalid` = `langid` WHERE `externalid` IS NULL;

INSERT INTO `executable` (`execid`, `description`, `type`) VALUES
('r', 'r', 'compile');

-- Copied from sql/mysql_db_files_defaultdata.sql after running make dist
UPDATE executable SET zipfile = 0x504B03040A000000000028AD624EC241F8B4200000002000000005001C006275696C6455540900036BEA7A5C6CEA7A5C75780B000104F5010000041400000023212F62696E2F73680A23206E6F7468696E6720746F20636F6D70696C650A0A504B030414000000080028AD624E04000029F70200005705000003001C0072756E55540900036BEA7A5C6CEA7A5C75780B000104F501000004140000006D54DB6EDA40107DAEBF626268915A42421FC9458DC04DA902545C54556D8596F5805731BBEEEE3A40A3FC7B677C6948551E2CEF7AE6CC993367689C9CAD943E73491034600AD26C339522ECACC832B4A74E5A9579581B0BADEA5BC725AD0E05CF10C127C2C3518C3B682FF620740C5B6311945E1B8AA5E879A25C1D191B74A08D07217D2ED2F40061851D12228233B995D88655EE8988F2142D08C22598A635465179A7E8629D6BE995D1205C918D7B94B917AB147BB04B5083A41218B74179022952CA103C2A46F73E29CED2588BD213758F36B348CFAAAD367DCF1D61109ED929BDA13335F51C18D760DEC00A217774E3ADD02E13F4F4D4275154EB8A08BD575DC79C20C079D24DA44623D048843D94CA8D27F3613FAA7A292A5669B6D682AE8A5A450F8B59B4EC7F9A4E2673309A292361C8C41AD2DBE2AF5C5992D3561495A6A2AC4E100CA2D9FC2A6C7643B820A5D5DA07A36874371C0DFFBDBD198E6793C5B41FD1FD63349E4FBF2DBF4C86E379EFB4D97D0A0B1B6166AC2F87A1BD3D406648A436A943ADD338349776E28187829D4DA7B04E2A58698B592A0EBD8054FA0EA7BF216C1E5508E1E705A3EA00E8873231100EC9613D88497DC90328EA2D8B7A3D6832D7FEDDCD6C16066BC5CCC60650B803897F6005C8599E4C5FCAE88E1CCCB1FD04E53D8FEBA80796FA41A42A2E099EC0A9258ACF92FC8F6164ADB13DD80AA56B7FAC79C55A47792D06D6C58444CCD60DE1FACDFB12644F9A752BFE5F791B2AFBB2014BE6BD40D2325C13131E62089797D1E463D0F8BBD90DB8458D56140EE525AAD79A24A85761FAC2F0A6A6DAE1AA83425D8895D5628BC576CB44E80DF2158350D603A9043215AEEC636D728A426EDD754AB1C21FCDC7F3D7676F9F4238B9E2D3F9B35AAF647CF4BD6A76A1C9E85BC66527A58AF70269F378260793D31079209E94BD471EAB22E23C428F62EB98566C2035B4FD8415E32ADF6C4832FEE7C27DE1CFC9F86E388E969F1783DBE8AA0B83C9A87A0D025605A6954A2F074C343F84010B1CC8646B6210EFF6B5F29C4884CE83E00F504B01021E030A000000000028AD624EC241F8B42000000020000000050018000000000001000000ED81000000006275696C6455540500036BEA7A5C75780B000104F50100000414000000504B01021E0314000000080028AD624E04000029F702000057050000030018000000000001000000ED815F00000072756E55540500036BEA7A5C75780B000104F50100000414000000504B0506000000000200020094000000930300000000, md5sum = 'e380afade7cc11c8f1ef0dcc156847b7' WHERE execid = 'r';

INSERT INTO `language` (`langid`, `externalid`, `name`, `extensions`, `require_entry_point`, `entry_point_description`, `allow_submit`, `allow_judge`, `time_factor`, `compile_script`)
VALUES ('r', 'r', 'R', '["R"]', 0, "Main file", 0, 1, 1, 'r');

--
-- Finally remove obsolete structures after moving data
--

