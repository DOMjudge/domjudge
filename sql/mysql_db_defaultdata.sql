-- These are default entries for the DOMjudge database, required for
-- correct functioning.
--
-- You can pipe this file into the 'mysql' command to insert this
-- data, but preferably use 'dj_setup_database'. Database should be set
-- externally (e.g. to 'domjudge').


--
-- Dumping data for table `configuration`
--

INSERT INTO `configuration` (`name`, `value`, `type`, `public`, `description`) VALUES
('clar_categories', '{"general":"General issue","tech":"Technical issue"}', 'array_keyval', '1', 'List of additional clarification categories'),
('clar_answers', '["No comment","Read the problem statement carefully"]', 'array_val', '0', 'List of predefined clarification answers'),
('clar_queues', '{}', 'array_keyval', '1', 'List of clarification queues'),
('clar_default_problem_queue', '""', 'string', '1', 'Queue to assign to problem clarifications'),
('script_timelimit', '30', 'int', '0', 'Maximum seconds available for compile/compare scripts. This is a safeguard against malicious code and buggy scripts, so a reasonable but large amount should do.'),
('script_memory_limit', '2097152', 'int', '0', 'Maximum memory usage (in kB) by compile/compare scripts. This is a safeguard against malicious code and buggy script, so a reasonable but large amount should do.'),
('script_filesize_limit', '540672', 'int', '0', 'Maximum filesize (in kB) compile/compare scripts may write. Submission will fail with compiler-error when trying to write more, so this should be greater than any *intermediate or final* result written by compilers.'),
('memory_limit', '524288', 'int', '0', 'Maximum memory usage (in kB) by submissions. This includes the shell which starts the compiled solution and also any interpreter like the Java VM, which takes away approx. 300MB! Can be overridden per problem.'),
('output_limit', '4096', 'int', '0', 'Maximum output (in kB) submissions may generate. Any excessive output is truncated, so this should be greater than the maximum testdata output.'),
('process_limit', '64', 'int', '0', 'Maximum number of processes that the submission is allowed to start (including shell and possibly interpreters).'),
('sourcesize_limit', '256', 'int', '1', 'Maximum source code size (in kB) of a submission. This setting should be kept in sync with that in "etc/submit-config.h.in".'),
('sourcefiles_limit', '100', 'int', '1', 'Maximum number of source files in one submission. Set to one to disable multiple file submissions.'),
('timelimit_overshoot', '"1s|10%"', 'string', '0', 'Time that submissions are kept running beyond timelimt before being killed. Specify as "Xs" for X seconds, "Y%" as percentage, or a combination of both separated by one of "+|&" for the sum, maximum, or minimum of both.'),
('output_storage_limit', '50000', 'int', '0', 'Maximum size of error/system output stored in the database (in bytes); use "-1" to disable any limits.'),
('output_display_limit', '2000', 'int', '0', 'Maximum size of run/diff/error/system output shown in the jury interface (in bytes); use "-1" to disable any limits.'),
('verification_required', '0', 'bool', '0', 'Is verification of judgings by jury required before publication?'),
('score_in_seconds', '0', 'bool', '1', 'Should the scoreboard resolution be measured in seconds instead of minutes?'),
('show_flags', '1', 'bool', '1', 'Show country flags in the interfaces?'),
('show_affiliations', '1', 'bool', '1', 'Show affiliation names in the interfaces?'),
('show_affiliation_logos', '0', 'bool', '1', 'Show affiliation logos on the scoreboard?'),
('show_pending', '0', 'bool', '1', 'Show pending submissions on the scoreboard?'),
('show_teams_submissions', '1', 'bool', '1', 'Show problem columns with submission information on the public and team scoreboards?'),
('show_compile', '2', 'int', '1', 'Show compile output in team webinterface? Choices: 0 = never, 1 = only on compilation error(s), 2 = always.'),
('show_sample_output', '0', 'bool', '1', 'Should teams be able to view a diff of their and the reference output to sample testcases?'),
('show_balloons_postfreeze', '0', 'bool', '1', 'Give out balloon notifications after the scoreboard has been frozen?'),
('penalty_time', '20', 'int', '1', 'Penalty time in minutes per wrong submission (if finally solved).'),
('compile_penalty', '1', 'bool', '1', 'Should submissions with compiler-error incur penalty time (and show on the scoreboard)?'),
('results_prio', '{"memory-limit":99,"output-limit":99,"run-error":99,"timelimit":99,"wrong-answer":30,"no-output":10,"correct":1}', 'array_keyval', '0', 'Priorities of results for determining final result with multiple testcases. Higher priority is used first as final result. With equal priority, the first occurring result determines the final result.'),
('results_remap', '{}', 'array_keyval', '0', 'Remap testcase result, e.g. to disable a specific result type such as ''no-output''.'),
('lazy_eval_results', '1', 'bool', '0', 'Lazy evaluation of results? If enabled, stops judging as soon as a highest priority result is found, otherwise always all testcases will be judged.'),
('enable_printing', '0', 'bool', '1', 'Enable teams and jury to send source code to a printer via the DOMjudge web interface.'),
('show_relative_time', '0', 'bool', '1', 'Print times of contest events relative to contest start (instead of absolute).'),
('time_format', '"%H:%M"', 'string', '0', 'The format used to print times. For formatting options see the PHP \'strftime\' function.'),
('default_compare', '"compare"', 'string', '0', 'The script used to compare outputs if no special compare script specified.'),
('default_run', '"run"', 'string', '0', 'The script used to run submissions if no special run script specified.'),
('allow_registration', '0', 'bool', '1', 'Allow users to register themselves with the system?'),
('allow_openid_auth', '0', 'bool', '1', 'Allow users to log in using OpenID'),
('openid_autocreate_team', '1', 'bool', '1', 'Create a team for each user that logs in with OpenID'),
('openid_provider', '"https://accounts.google.com"', 'string', '1', 'OpenID Provider URL'),
('openid_clientid', '""', 'string', '0','OpenID Connect client id'),
('openid_clientsecret', '""', 'string', '0', 'OpenID Connect client secret'),
('judgehost_warning', '30', 'int', '0', 'Time in seconds after a judgehost last checked in before showing its status as "warning".'),
('judgehost_critical', '120', 'int', '0', 'Time in seconds after a judgehost last checked in before showing its status as "critical".'),
('thumbnail_size', '128', 'int', '0', 'Maximum width/height of a thumbnail for uploaded testcase images.'),
('diskspace_error', '1048576', 'int', '0', 'Minimum free disk space (in kB) on judgehosts.'),
('show_limits_on_team_page', '0', 'bool', '1', 'Show time and memory limit on the team problems page');

--
-- Dumping data for table `executable`
--

INSERT INTO `executable` (`execid`, `description`, `type`) VALUES
('adb', 'adb', 'compile'),
('awk', 'awk', 'compile'),
('bash', 'bash', 'compile'),
('c', 'c', 'compile'),
('compare', 'default compare script', 'compare'),
('cpp', 'cpp', 'compile'),
('csharp', 'csharp', 'compile'),
('f95', 'f95', 'compile'),
('float', 'default compare script for floats with prec 1E-7', 'compare'),
('hs', 'hs', 'compile'),
('kt', 'kt', 'compile'),
('java_gcj', 'java_gcj', 'compile'),
('java_javac', 'java_javac', 'compile'),
('java_javac_detect', 'java_javac_detect', 'compile'),
('js', 'js', 'compile'),
('lua', 'lua', 'compile'),
('pas', 'pas', 'compile'),
('pl', 'pl', 'compile'),
('plg', 'plg', 'compile'),
('py2', 'py2', 'compile'),
('py3', 'py3', 'compile'),
('rb', 'rb', 'compile'),
('run', 'default run script', 'run'),
('scala', 'scala', 'compile'),
('sh', 'sh', 'compile');

--
-- Dumping data for table `language`
--

INSERT INTO `language` (`langid`, `externalid`, `name`, `extensions`, `require_entry_point`, `entry_point_description`, `allow_submit`, `allow_judge`, `time_factor`, `compile_script`) VALUES
('adb', NULL, 'Ada', '["adb","ads"]', 0, NULL, 0, 1, 1, 'adb'),
('awk', NULL, 'AWK', '["awk"]', 0, NULL, 0, 1, 1, 'awk'),
('bash', NULL, 'Bash shell', '["bash"]', 0, NULL, 0, 1, 1, 'bash'),
('c', 'c', 'C', '["c"]', 0, NULL, 1, 1, 1, 'c'),
('cpp', 'cpp', 'C++', '["cpp","cc","cxx","c++"]', 0, NULL, 1, 1, 1, 'cpp'),
('csharp', 'csharp', 'C#', '["csharp","cs"]', 0, NULL, 0, 1, 1, 'csharp'),
('f95', NULL, 'Fortran', '["f95","f90"]', 0, NULL, 0, 1, 1, 'f95'),
('hs', 'haskell', 'Haskell', '["hs","lhs"]', 0, NULL, 0, 1, 1, 'hs'),
('java', 'java', 'Java', '["java"]', 0, "Main class", 1, 1, 1, 'java_javac_detect'),
('js', 'javascript', 'JavaScript', '["js"]', 0, NULL, 0, 1, 1, 'js'),
('lua', NULL, 'Lua', '["lua"]', 0, NULL, 0, 1, 1, 'lua'),
('kt', 'kotlin', 'Kotlin', '["kt"]', 1, "Main class", 0, 1, 1, 'kt'),
('pas', 'pascal', 'Pascal', '["pas","p"]', 0, NULL, 0, 1, 1, 'pas'),
('pl', NULL, 'Perl', '["pl"]', 0, NULL, 0, 1, 1, 'pl'),
('plg', 'prolog', 'Prolog', '["plg"]', 0, NULL, 0, 1, 1, 'plg'),
('py2', 'python2', 'Python 2', '["py2","py"]', 1, "Main file", 0, 1, 1, 'py2'),
('py3', 'python3', 'Python 3', '["py3"]', 1, "Main file", 0, 1, 1, 'py3'),
('rb', NULL, 'Ruby', '["rb"]', 0, NULL, 0, 1, 1, 'rb'),
('scala', 'scala', 'Scala', '["scala"]', 0, NULL, 0, 1, 1, 'scala'),
('sh', NULL, 'POSIX shell', '["sh"]', 0, NULL, 0, 1, 1, 'sh');


--
-- Dumping data for table `role`
--

INSERT INTO `role` (`roleid`, `role`, `description`) VALUES
(1, 'admin',             'Administrative User'),
(2, 'jury',              'Jury User'),
(3, 'team',              'Team Member'),
(4, 'balloon',           'Balloon runner'),
(5, 'print',             'print'),
(6, 'judgehost',         '(Internal/System) Judgehost'),
(7, 'event_reader',      '(Internal/System) event_reader'),
(8, 'full_event_reader', '(Internal/System) full_event_reader');

--
-- Dumping data for table `team_category`
--
-- System category
INSERT INTO `team_category` (`categoryid`, `name`, `sortorder`, `color`, `visible`) VALUES
(1, 'System', 9, '#ff2bea', 0),
(2, 'Self-Registered', 8, '#33cc44', 1);

--
-- Dumping data for table `team`
--

INSERT INTO `team` (`teamid`, `name`, `categoryid`, `affilid`, `hostname`, `room`, `comments`, `teampage_first_visited`) VALUES
(1, 'DOMjudge', 1, NULL, NULL, NULL, NULL, NULL);

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`userid`, `username`, `name`, `email`, `password`) VALUES
(1, 'admin', 'Administrator', 'team@domjudge.org', '$2y$10$WkXRuj/UgoMGF80BaqhOJ.b1HW8KcGrUcWV3uAvGrQlp6Ia8w/dgO'), -- Is a hash for 'admin'
(2, 'judgehost', 'User for judgedaemons', NULL, NULL);

--
-- Dumping data for table `userrole`
--

INSERT INTO `userrole` (`userid`, `roleid`) VALUES
(1, 1),
(2, 6);
