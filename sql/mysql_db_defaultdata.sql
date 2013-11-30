-- These are default entries for the DOMjudge database, required for
-- correct functioning.
--
-- You can pipe this file into the 'mysql' command to insert this
-- data, but preferably use 'dj-setup-database'. Database should be set
-- externally (e.g. to 'domjudge').


-- 
-- Dumping data for table `configuration`
-- 

INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('compile_time', '30', 'int', 'Maximum seconds available for compiling.');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('memory_limit', '524288', 'int', 'Maximum memory usage (in kB) by submissions. This includes the shell which starts the compiled solution and also any interpreter like the Java VM, which takes away approx. 300MB!');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('filesize_limit', '4096', 'int', 'Maximum filesize (in kB) submissions may write. Solutions will abort when trying to write more, so this should be greater than the maximum testdata output.');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('process_limit', '15', 'int', 'Maximum number of processes that the submission is allowed to start (including shell and possibly interpreters).');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('sourcesize_limit', '256', 'int', 'Maximum source code size (in kB) of a submission. This setting should be kept in sync with that in "etc/submit-config.h.in".');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('sourcefiles_limit', '100', 'int', 'Maximum number of source files in one submission. Set to one to disable multiple file submissions.');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('timelimit_overshoot', '"1s|10%"', 'string', 'Time that submissions are kept running beyond timelimt before being killed. Specify as "Xs" for X seconds, "Y%" as percentage, or a combination of both separated by one of "+|&" for the sum, maximum, or minimum of both.');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('verification_required', '0', 'bool', 'Is verification of judgings by jury required before publication?');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('show_affiliations', '1', 'bool', 'Show affiliations names and icons in the scoreboard?');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('show_pending', '0', 'bool', 'Show pending submissions on the scoreboard?');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('show_compile', '2', 'int', 'Show compile output in team webinterface? Choices: 0 = never, 1 = only on compilation error(s), 2 = always.');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('show_balloons_postfreeze', '0', 'bool', 'Give out balloon notifications after the scoreboard has been frozen?');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('penalty_time', '20', 'int', 'Penalty time in minutes per wrong submission (if finally solved).');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('results_prio', '{"memory-limit":99,"output-limit":99,"run-error":99,"timelimit":99,"wrong-answer":30,"presentation-error":20,"no-output":10,"correct":1}', 'array_keyval', 'Priorities of results for determining final result with multiple testcases. Higher priority is used first as final result. With equal priority, the first occurring result determines the final result.');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('results_remap', '{"presentation-error":"wrong-answer"}', 'array_keyval', 'Remap testcase result, e.g. to disable a specific result type such as ''presentation-error''.');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('lazy_eval_results', '1', 'bool', 'Lazy evaluation of results? If enabled, stops judging as soon as a highest priority result is found, otherwise always all testcases will be judged.');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('enable_printing', '0', 'bool', 'Enable teams and jury to send source code to a printer via the DOMjudge web interface.');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('time_format', '"H:i"', 'string', 'The format used to print times. For formatting options see the PHP \'date\' function.');

-- 
-- Dumping data for table `language`
-- 

INSERT INTO `language` (`langid`, `name`, `extensions`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('c', 'C', '["c"]', 1, 1, 1);
INSERT INTO `language` (`langid`, `name`, `extensions`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('cpp', 'C++', '["cpp","cc","c++"]', 1, 1, 1);
INSERT INTO `language` (`langid`, `name`, `extensions`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('java', 'Java', '["java"]', 1, 1, 1.5);
INSERT INTO `language` (`langid`, `name`, `extensions`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('pas', 'Pascal', '["pas","p"]', 0, 1, 1);
INSERT INTO `language` (`langid`, `name`, `extensions`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('hs', 'Haskell', '["hs","lhs"]', 0, 1, 2);
INSERT INTO `language` (`langid`, `name`, `extensions`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('pl', 'Perl', '["pl"]', 0, 1, 1);
INSERT INTO `language` (`langid`, `name`, `extensions`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('sh', 'POSIX shell', '["sh"]', 0, 1, 1);
INSERT INTO `language` (`langid`, `name`, `extensions`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('bash', 'Bash shell', '["bash"]', 0, 1, 1);
INSERT INTO `language` (`langid`, `name`, `extensions`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('csharp', 'C#', '["csharp","cs"]', 0, 1, 1);
INSERT INTO `language` (`langid`, `name`, `extensions`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('awk', 'AWK', '["awk"]', 0, 1, 1);
INSERT INTO `language` (`langid`, `name`, `extensions`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('py2', 'Python 2', '["py2","py"]', 0, 1, 1);
INSERT INTO `language` (`langid`, `name`, `extensions`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('py3', 'Python 3', '["py3"]', 0, 1, 1);
INSERT INTO `language` (`langid`, `name`, `extensions`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('adb', 'Ada', '["adb","ads"]', 0, 1, 1);
INSERT INTO `language` (`langid`, `name`, `extensions`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('f95', 'Fortran', '["f95","f90"]', 0, 1, 1);
INSERT INTO `language` (`langid`, `name`, `extensions`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('scala', 'Scala', '["scala"]', 0, 1, 1.5);
INSERT INTO `language` (`langid`, `name`, `extensions`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('lua', 'Lua', '["lua"]', 0, 1, 1);
INSERT INTO `language` (`langid`, `name`, `extensions`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('plg', 'Prolog', '["plg"]', 0, 1, 1);
INSERT INTO `language` (`langid`, `name`, `extensions`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('rb', 'Ruby', '["rb"]', 0, 1, 1);

-- 
-- Dumping data for table `role`
-- 

INSERT INTO `role` (`roleid`, `role`, `description`) VALUES (1, 'admin',          'Administrative User');
INSERT INTO `role` (`roleid`, `role`, `description`) VALUES (2, 'jury',           'Jury User');
INSERT INTO `role` (`roleid`, `role`, `description`) VALUES (3, 'team',           'Team Member');
INSERT INTO `role` (`roleid`, `role`, `description`) VALUES (4, 'balloon',        'Balloon runner');
INSERT INTO `role` (`role`, `description`) VALUES ('print',             'print');
INSERT INTO `role` (`role`, `description`) VALUES ('judgehost',         '(Internal/System) Judgehost');
INSERT INTO `role` (`role`, `description`) VALUES ('event_reader',      '(Internal/System) event_reader');
INSERT INTO `role` (`role`, `description`) VALUES ('full_event_reader', '(Internal/System) full_event_reader');

-- 
-- Dumping data for table `team_category`
-- 
-- System category
INSERT INTO `team_category` VALUES (1, 'System', 9, '#ff2bea', 0);

-- 
-- Dumping data for table `team`
-- 

INSERT INTO `team` (`login`, `name`, `categoryid`, `affilid`, `hostname`, `room`, `comments`, `teampage_first_visited`) VALUES ('domjudge', 'DOMjudge', 1, NULL, NULL, NULL, NULL, NULL);

-- 
-- Dumping data for table `user`
-- 

INSERT INTO `user` (`userid`, `username`, `name`, `password`) VALUES ('1', 'admin', 'Administrator', MD5('admin#admin'));

-- 
-- Dumping data for table `userrole`
-- 

INSERT INTO `userrole` (`userid`, `roleid`) VALUES ('1', '1');
