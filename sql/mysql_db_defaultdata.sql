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
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('show_sample_output', '0', 'bool', 'Should teams be able to view a diff of their and the reference output to sample testcases?');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('show_balloons_postfreeze', '0', 'bool', 'Give out balloon notifications after the scoreboard has been frozen?');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('penalty_time', '20', 'int', 'Penalty time in minutes per wrong submission (if finally solved).');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('results_prio', '{"memory-limit":99,"output-limit":99,"run-error":99,"timelimit":99,"wrong-answer":30,"presentation-error":20,"no-output":10,"correct":1}', 'array_keyval', 'Priorities of results for determining final result with multiple testcases. Higher priority is used first as final result. With equal priority, the first occurring result determines the final result.');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('results_remap', '{"presentation-error":"wrong-answer"}', 'array_keyval', 'Remap testcase result, e.g. to disable a specific result type such as ''presentation-error''.');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('lazy_eval_results', '1', 'bool', 'Lazy evaluation of results? If enabled, stops judging as soon as a highest priority result is found, otherwise always all testcases will be judged.');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('enable_printing', '0', 'bool', 'Enable teams and jury to send source code to a printer via the DOMjudge web interface.');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('time_format', '"%H:%M"', 'string', 'The format used to print times. For formatting options see the PHP \'strftime\' function.');

--
-- Dumping data for table `executable`
--

INSERT INTO `executable` (`execid`, `md5sum`, `zipfile`, `description`) VALUES
('boolfind', 'bfd8b32d72d277977d25353a86ddf653', 0x504b0304140000000800e4564544718260ff3e0000004c00000005001c006275696c6455540900037b0af2527b0af25275780b000104e803000004e80300005356d44fcaccd32fcee0e24a4f4e56d00d4fccc951d04dcc2bce54d02d484d49cc2bc9048afa1b292467a42667c727e5e7e7a465e6a5e80105f3d1c4b800504b030414000000080084564544f6a40647050300003507000010001c00636865636b5f626f6f6c66696e642e635554090003c809f252c809f25275780b000104e803000004e80300008d55c16edb300c3ddb5fc1ba486ba7ce9004e802247177da8001c5b6437b6a73506dd915e648992477ebd6fefb48c94e9d2ecb9683215114df7be4b3732c645e370587a5b185506fee2fc2e37ea81677af635ac86a3796dbc70da750f8e1e3e57b185a6eac90290c375a55aab18b3014d2c29a0919d382e92a4f21bf671a8643dc3c24e1af30a013dde035894fb308835ac90a36ca60e44ea99a429433ec36ae402d24bf999ebf5db5a7220544dc34f681d5a240e44094103bc8d164398504102a2837a8c296312ae45aa7113e949e835416b8544d754f179a3597d6cc610a9a7f6b84e6c5ad8c12c40934b78d9630c1f5731878b90019946ac3654c8a6e26ab34d22ebb6dc2eef1b43b6ef9f91a59f6e9faf2129e9ea0bdd4060e92ce9924de541b4a5173b3976518940633cbb81d4e342035e9896bb6e3512a1de3269b2c680ccbcc8f81d667672d815715eab6841b88ab11b8254a5db3ba5679ec8f8646fce4aaa4d9278e9993ec8e7604fe43a12bc92c87355f2bfdd8c9eceb74429d12f44d365e907d96ad7b684d4af6f7c1e5dc60caca0b7114cb8a5b1393c5d2e9f979da0e25d925dd728ea860ce0c8741b1a5ac392b9c4509033be90957ca2a90fc87dd3216d8327cb36ab40765272327e6fb3d8e1369888b6c0c2727deeb629565a7b7f214e1fd7e345ae1edb163ddb33ec626db5e636d99af375e4af4f9faeacbf51544e92c39c2ca4f4f9806f4eb5358cec8870e6246906342dcadef31a9db229b2d402cfbf771dff9c6733812a61095b0712b23e91a18fc51958277d8bbaf6ef9bced922bd3cf3e3402217d8ecf875cadd74c16703a30a7ed34524f74ff488cb7c94ec7d0f1681534c98b8bc96063f7c62a739179ab8d26078961a6b042492a47ec4095a099ac3a93a41dc03ea3b816bc989590b7bbb3c9eabf815302cf6060d281e9e1a6ae4c4a25c0ffe21e18c03b88ac6e780473884a561b1e25fb9389cb9ee4bfe802b79b939b34e771f741716787dec4a3fe9bd869c64a9a41c12c03565a8eff11cc58e83a31bf9503136d47efbe8c79ad0c6fbf08146c031d0ad9bcfdc8a0399fc3df504b0304140000000800b356454430b828dd15030000ae06000003001c0072756e5554090003210af252210af25275780b000104e803000004e80300009d54ef53133110fd4cfe8a255401c7b680faa5020e538a7654ca40199d711c26cded71d16b7293e4f821c3ffee26773d7ac807f53e2597ddb76fdfbe646db53f53baef32c6d66068e685b008d7561405daae9356151ebc81198214798e09a4d6cc61dda3f35238bcb0a5eeb96cbdc7d628ffdc894b1c40670b76434022bce829bd0fbb85359756cc7ba6f4fb4b67d5d6a22b735f6f1295a6711901db3000472a2722467ba1b4d297b03806a58bd2f74246abd4131919421d0214b2c86a537a324b1a6b51fae5ac65e68b5a0906d5668bac1f657219d657681325abb4a64780268d34beb6ca37e4faed721072d0a296e842ec8629bc325ae49b5179421ddd28efc09592225c5ae6f92de08d441adf75861a84268d3c5a4a01b4d658305296d6f5e0ac9ccd95f79810485b19502e88e054429593468088b7e821c4e0bcf0b7b021748070054a952a4c2a6630cd28a23652b090232a8694b14d316a4766287f46bd2a495dd06948d1413a774b73b8194434fa3ac30fa3e1c78b93d3c9fbd383cfffe0b49a0e3e46709929f3842afbd2ea7ad4249526394dba3c2d6f427f9efa143659f800c61ee6a5f3a4b6226d94cfe24a9a04e1175a13da539a662f3c86f4664055d3d4608fb116a13ddeb9db7ad67f71df8f11173363f2942078b8a29338f7e8814238f7205eddf8a0c69a9c4cc793e3b33d4e59d3d1d9747c4cb0db9c3dd4d8e1f160723ea5cd2bce4e4767e79fc2fa356787e3a3a3eae0eecda0db4ff0aaafc952f704165d5a4d696393ddb130920d9aac87ddddd1e48855477b9d6d1676e17413f681772a78ceee1953297c8355e8ded0ef56e71cbebf0d03d06c056566808f825307b0de0e5b077210a4a6d434054b72a32cbd98e5d8e3b0ff7c87ad2c71043e6ed99e137418d4364b55d0f3b4d4cb6fc280fd41a9d31294285772725a3531d54f528cc75e6bf9381b7d1d4f8793c3d15ee71dabef68659154a8bcb4f8b2ba4cdae86ef44ae39cd8dca052aab34081ae46d8fa5b8d0216dddab6231bacff906a187d56c7d225a81facfa474aaf584db8eb963478a0db2af5c51abada42bb6ba442983b7c147020c3e385e4fa503d12d962bf01504b01021e03140000000800e4564544718260ff3e0000004c000000050018000000000001000000ed81000000006275696c6455540500037b0af25275780b000104e803000004e8030000504b01021e0314000000080084564544f6a406470503000035070000100018000000000001000000a4817d000000636865636b5f626f6f6c66696e642e635554050003c809f25275780b000104e803000004e8030000504b01021e03140000000800b356454430b828dd15030000ae060000030018000000000001000000ed81cc03000072756e5554050003210af25275780b000104e803000004e8030000504b05060000000003000300ea0000001e0700000000, 'boolfind');

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
