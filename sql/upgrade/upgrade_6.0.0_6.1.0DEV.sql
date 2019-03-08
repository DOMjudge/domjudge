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

UPDATE `configuration` SET `name` = 'registration_category_name',
  `value` = IF(`value` = '0', '""', '"Self-Registered"'),
  `type` = 'string',
  `description` = 'Team category for users that register themselves with the system. Disabled if empty.'
  WHERE `name` = 'allow_registration';

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
UPDATE executable SET zipfile = 0x504B03040A000000000074AA684EC241F8B4200000002000000005001C006275696C6455540900035CCE825C8CCE825C75780B000104F5010000041400000023212F62696E2F73680A23206E6F7468696E6720746F20636F6D70696C650A0A504B030414000000080042AB684E5BA3ADADF50200005905000003001C0072756E5554090003DCCF825CDCCF825C75780B000104F501000004140000006D545D6FD340107CC6BF62EB062A4193B63CA61F023506829A04A5A91002545DCEEBF854E7CEDC9D9B84AAFF9DDDB34D5D441EA2F8BC373B3B339BFDBDA3A5D2472E8FA27D988334EB5215081B2BCA126DDF49AB4A0F99B170D0BC1BB8FC6040C5D788E073E1A153E376DA8B2D089DC2DA5804A53343B554BDC8956B2B53830EB4F120A4AF4451EC206EB063424470A6B2120F61597922A23C550B82703916458B113A6F141D6495965E190DC285DBB8455979B12C70089B1C35486A81E921284F20E14A5D829D6674EEF3F02C8DB5283D51F7684B8BF4DD8C7548EF2B47188467364AAFE899867A2A4C5B306F608950393AF15668570AFAF6342751545943847E3753A77C4180F3A49B288C46204B84DDD5CA4D678BF165D2CC123A36D76CAB051D855E61869BEBE4F6F2D37C365B80D14C190943E6D690DE167F55CA929CB6A1A834356575A268945C2FCEE3DE490CA7A4B4CA7C34492657E3C9F8DFD3F7E3E9F5EC667E99D0F943325DCCBFDD7E998DA78B61BF77F21887186169ACAFCDD0DEEEA03424D221A943A3931D9A5B3B71CFA6E0603508D129042B6DB12CC46E18914ADFA1FF1BE25EA7430C3F4F195547401F94B981784C091B424AEA4B3620F4BB0DFD86D07BE21A4799626A530328DC8ED4DFB104142D4FA9AF75749D0873ED658EF28EFDEA0CC15ADF8B42A535C33DE85BE2D8E9F31F8A89B5C60E612D946E0392F18E1D74EE1D30B00E168994B31BC3C5ABB735C896443B09FC89D457DE8726C01CC19AFA3092B40E1744856D8CE1EC2C997D88F6FFEEF63E7C448D56848CF21AB58B4D1AB4CB307F1679D3721D70D751D0175265B55863D86F990BBD423E6210BA754F32812C84AB07C94C4555C8B3BB41AD56FCA3F770FCF2E8F5630C7BE7FC74FC24D70B9976DE376EDD688AFA9A71394B85E2CD40DA3D3665672A72911DF124ED1DB2AF8A88B3871EC5DA31ADD4406168FF092BC565B55A9164FCDF85DB90D0D9F46A3C4D6E3FDF8C3E26E727309A4D9A9F51C4AAC0BC51E9B9C344F35D1CB1C091CCD72605F166DB2ACF1789D07114FD01504B01021E030A000000000074AA684EC241F8B42000000020000000050018000000000001000000ED81000000006275696C6455540500035CCE825C75780B000104F50100000414000000504B01021E0314000000080042AB684E5BA3ADADF502000059050000030018000000000001000000ED815F00000072756E5554050003DCCF825C75780B000104F50100000414000000504B0506000000000200020094000000910300000000, md5sum = 'fe1a2827245169fcc939f2db6d2821d6' WHERE execid = 'r';

INSERT INTO `language` (`langid`, `externalid`, `name`, `extensions`, `require_entry_point`, `entry_point_description`, `allow_submit`, `allow_judge`, `time_factor`, `compile_script`)
VALUES ('r', 'r', 'R', '["R"]', 0, "Main file", 0, 1, 1, 'r');

--
-- Finally remove obsolete structures after moving data
--

