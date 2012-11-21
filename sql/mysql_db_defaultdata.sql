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
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('verification_required', '0', 'bool', 'Is verification of judgings by jury required before publication?');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('show_affiliations', '1', 'bool', 'Show affiliations names and icons in the scoreboard?');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('show_pending', '0', 'bool', 'Show pending submissions on the scoreboard?');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('show_compile', '2', 'int', 'Show compile output in team webinterface? Choices: 0 = never, 1 = only on compilation error(s), 2 = always.');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('show_balloons_postfreeze', '0', 'bool', 'Give out balloon notifications after the scoreboard has been frozen?');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('penalty_time', '20', 'int', 'Penalty time in minutes per wrong submission (if finally solved).');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('results_prio', '{"memory-limit":99,"output-limit":99,"run-error":99,"timelimit":99,"wrong-answer":30,"presentation-error":20,"no-output":10,"correct":1}', 'array_keyval', 'Priorities of results for determining final result with multiple testcases. Higher priority is used first as final result. With equal priority, the first occurring result determines the final result.');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('results_remap', '{"presentation-error":"wrong-answer"}', 'array_keyval', 'Remap testcase result, e.g. to disable a specific result type such as ''presentation-error''.');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('lazy_eval_results', '1', 'bool', 'Lazy evaluation of results? If enabled, stops judging as soon as a highest priority result is found, otherwise always all testcases will be judged.');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('team_select_js', '1', 'bool', 'Enable selection of favourite teams in the public scoreboard?');

-- 
-- Dumping data for table `language`
-- 

INSERT INTO `language` (`langid`, `name`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('c', 'C', 1, 1, 1);
INSERT INTO `language` (`langid`, `name`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('cpp', 'C++', 1, 1, 1);
INSERT INTO `language` (`langid`, `name`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('java', 'Java', 1, 1, 1.5);
INSERT INTO `language` (`langid`, `name`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('pas', 'Pascal', 0, 1, 1);
INSERT INTO `language` (`langid`, `name`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('hs', 'Haskell', 0, 1, 2);
INSERT INTO `language` (`langid`, `name`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('pl', 'Perl', 0, 1, 1);
INSERT INTO `language` (`langid`, `name`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('sh', 'POSIX shell', 0, 1, 1);
INSERT INTO `language` (`langid`, `name`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('bash', 'Bash shell', 0, 1, 1);
INSERT INTO `language` (`langid`, `name`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('csharp', 'C#', 0, 1, 1);
INSERT INTO `language` (`langid`, `name`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('awk', 'AWK', 0, 1, 1);
INSERT INTO `language` (`langid`, `name`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('py', 'Python', 0, 1, 1);

