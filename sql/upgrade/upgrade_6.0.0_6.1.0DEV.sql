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

INSERT INTO `language` (`langid`, `externalid`, `name`, `extensions`, `require_entry_point`, `entry_point_description`, `allow_submit`, `allow_judge`, `time_factor`, `compile_script`)
VALUES ('r', 'r', 'R', '["R"]', 0, "Main file", 0, 1, 1, 'r');

--
-- Finally remove obsolete structures after moving data
--

