# These are the database tables needed for DOMjudge
# This assumes database name 'domjudge'
#
# You can pipe this file into the 'mysql' command to create the
# database and tables.
#
# $Id$

# Create and use the database:
CREATE DATABASE domjudge;
USE domjudge;

#
# Table structure for table `category`
#

CREATE TABLE `category` (
  `catid` mediumint(8) unsigned NOT NULL auto_increment,
  `name` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`catid`)
) TYPE=MyISAM;

#
# Table structure for table `clarification`
#

CREATE TABLE `clarification` (
  `clarid` mediumint(8) unsigned NOT NULL auto_increment,
  `cid` mediumint(8) unsigned NOT NULL default '0',
  `respid` mediumint(8) unsigned default NULL,
  `submittime` datetime NOT NULL default '0000-00-00 00:00:00',
  `sender` varchar(15) default NULL,
  `recipient` varchar(15) default NULL,
  `body` text NOT NULL,
  `answered` tinyint(4) unsigned NOT NULL default '0',
  PRIMARY KEY  (`clarid`)
) TYPE=MyISAM;

#
# Table structure for table `contest`
#

CREATE TABLE `contest` (
  `cid` mediumint(8) unsigned NOT NULL auto_increment,
  `starttime` datetime NOT NULL default '0000-00-00 00:00:00',
  `lastscoreupdate` datetime default NULL,
  `endtime` datetime NOT NULL default '0000-00-00 00:00:00',
  `contestname` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`cid`)
) TYPE=MyISAM;

#
# Table structure for table `judger`
#

CREATE TABLE `judger` (
  `judgerid` varchar(50) NOT NULL default '',
  `active` tinyint(8) unsigned NOT NULL default '1',
  PRIMARY KEY  (`judgerid`)
) TYPE=MyISAM;

#
# Table structure for table `judging`
#

CREATE TABLE `judging` (
  `judgingid` mediumint(10) unsigned NOT NULL auto_increment,
  `cid` mediumint(2) unsigned NOT NULL default '0',
  `submitid` mediumint(10) unsigned NOT NULL default '0',
  `starttime` datetime NOT NULL default '0000-00-00 00:00:00',
  `endtime` datetime default NULL,
  `judgerid` varchar(50) NOT NULL default '',
  `result` enum('correct','compiler-error','timelimit','run-error','wrong-answer','no-output') default NULL,
  `verified` tinyint(1) unsigned NOT NULL default '0',
  `valid` tinyint(1) unsigned NOT NULL default '1',
  `output_compile` text,
  `output_run` text,
  `output_diff` text,
  `output_error` text,
  PRIMARY KEY  (`judgingid`)
) TYPE=MyISAM;

#
# Table structure for table `language`
#

CREATE TABLE `language` (
  `langid` varchar(8) NOT NULL default '',
  `name` varchar(255) NOT NULL default '',
  `extension` varchar(5) NOT NULL default '',
  `allow_submit` tinyint(1) unsigned NOT NULL default '1',
  `allow_judge` tinyint(1) unsigned NOT NULL default '1',
  `time_factor` float NOT NULL default '0',
  PRIMARY KEY  (`langid`)
) TYPE=MyISAM;

#
# Table structure for table `problem`
#

CREATE TABLE `problem` (
  `probid` varchar(8) NOT NULL default '',
  `cid` mediumint(10) unsigned NOT NULL default '0',
  `name` varchar(255) NOT NULL default '',
  `allow_submit` tinyint(1) unsigned NOT NULL default '0',
  `allow_judge` tinyint(1) unsigned NOT NULL default '1',
  `testdata` varchar(255) NOT NULL default '',
  `timelimit` mediumint(11) unsigned NOT NULL default '0',
  PRIMARY KEY  (`probid`)
) TYPE=MyISAM;

#
# Table structure for table `scoreboard_jury`
#

CREATE TABLE `scoreboard_jury` (
  `cid` mediumint(8) unsigned NOT NULL default '0',
  `team` varchar(15) NOT NULL default '',
  `problem` varchar(8) NOT NULL default '',
  `submissions` int(3) unsigned NOT NULL default '0',
  `totaltime` int(4) unsigned NOT NULL default '0',
  `penalty` int(4) unsigned NOT NULL default '0',
  `is_correct` tinyint(1) unsigned NOT NULL default '0',
  PRIMARY KEY  (`cid`,`team`,`problem`)
) TYPE=MyISAM COMMENT='Scoreboard cache (jury version)';

#
# Table structure for table `scoreboard_public`
#

CREATE TABLE `scoreboard_public` (
  `cid` mediumint(8) unsigned NOT NULL default '0',
  `team` varchar(15) NOT NULL default '',
  `problem` varchar(8) NOT NULL default '',
  `submissions` int(3) unsigned NOT NULL default '0',
  `totaltime` int(4) unsigned NOT NULL default '0',
  `penalty` int(4) unsigned NOT NULL default '0',
  `is_correct` tinyint(1) unsigned NOT NULL default '0',
  PRIMARY KEY  (`cid`,`team`,`problem`)
) TYPE=MyISAM COMMENT='Scoreboard cache (public/team version)';

#
# Table structure for table `submission`
#

CREATE TABLE `submission` (
  `submitid` mediumint(10) unsigned NOT NULL auto_increment,
  `cid` mediumint(2) NOT NULL default '0',
  `team` varchar(15) NOT NULL default '',
  `probid` varchar(8) NOT NULL default '',
  `langid` varchar(8) NOT NULL default '',
  `submittime` datetime NOT NULL default '0000-00-00 00:00:00',
  `sourcefile` varchar(255) NOT NULL default '',
  `sourcecode` text NOT NULL,
  `judgerid` varchar(50) default NULL,
  `judgemark` varchar(255) default NULL,
  PRIMARY KEY  (`submitid`),
  UNIQUE KEY `judgemark` (`judgemark`)
) TYPE=MyISAM;

#
# Table structure for table `team`
#

CREATE TABLE `team` (
  `login` varchar(15) NOT NULL default '',
  `name` varchar(255) NOT NULL default '',
  `category` mediumint(4) unsigned NOT NULL default '0',
  `ipaddress` varchar(15) default NULL,
  PRIMARY KEY  (`login`),
  UNIQUE KEY `ipadres` (`ipaddress`)
) TYPE=MyISAM;
