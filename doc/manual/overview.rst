Overview
========

DOMjudge is a system for running programming contests, like the ICPC
regional and world finals programming contests.

This usually means that teams are on-site and have a fixed time period (mostly
5 hours) and one computer to solve a number of problems (mostly 8-12). Problems
are solved by writing a program in one of the allowed languages, that reads
input according to the problem input specification and writes the correct,
corresponding output.

The judging is done by submitting the source code of the solution to the jury.
There the jury system automatically compiles and runs the program and compares
the program output with the expected output.

This software can be used to handle the submission and judging during such
contests. It also handles feedback to the teams and communication on problems
(clarification requests). It has web interfaces for the jury, the teams (their
submissions and clarification requests) and the public (scoreboard).

Features
--------

A global overview of the features that DOMjudge provides:

* Automatic judging with distributed (scalable) judge hosts
* Web interface for portability and simplicity
* Modular system for plugging in languages/compilers and validators
* Detailed jury information (submissions, judgings, diffs)
  and options (rejudge, clarifications, resubmit)
* Designed with security in mind

DOMjudge has been used in many live contests
(see https://www.domjudge.org/about for an overview) and
is Open Source, Free Software.


Requirements and contest planning
---------------------------------

DOMjudge requires the following to be available to run. Please refer to the
:doc:`DOMserver <install-domserver>` and :doc:`Judgehost <install-judgehost>`
chapters for detailed software requirements.

* At least one machine to act as the *DOMjudge server* (or *domserver* for
  brevity). The machine needs to be running Linux (or possibly a Unix
  variant) and a webserver with PHP 7.2.5 or newer. A MySQL or MariaDB
  database is also needed.

* A number of machines to act as *judgehosts* (at least one). They need to run
  Linux with (sudo) root access. Required software is the PHP commandline
  client and compilers for the languages you want to support.

* *Team workstations*, one for each team. They require only a modern
  web browser to interface with DOMjudge, but of course need a local
  development environment for teams to develop and test solutions. Optionally
  these have the DOMjudge submit client installed.

* *Jury / admin workstations*. The jury members (persons) that want to
  configure and monitor the contest need just any workstation with a web
  browser to access the web interface. No DOMjudge software runs on these
  machines.

One (virtual) machine is required to run the DOMserver. The minimum amount of
judgehosts is also one, but preferably more: depending on configured timelimits,
and the amount of testcases per problem, judging one solution can tie up a
judgehost for several minutes, and if there's a problem with one judgehost it
can be resolved while judging continues on the others.

As a rule of thumb, we recommend one judgehost per 20 teams.

However, overprovisioning does not hurt: DOMjudge scales easily in the number
of judgehosts, so if hardware is available, by all means use it. But running a
contest with fewer machines will equally work well, only the waiting time for
teams to receive an answer may increase.

Each judgehost should be a dedicated (virtual) machine that performs no other
tasks. For example, although running a judgehost on the same machine as the
domserver is possible, it's not recommended except for testing purposes.
Judgehosts should also not double as local workstations for jury members.
Having all judgehosts be of uniform hardware configuration helps in creating a
fair, :ref:`reproducible setup <judging-consistency>`; in the ideal case
they are run on the same type of machines that the teams use.

DOMjudge supports running :ref:`multiple judgedaemons <multiple-judgedaemons>`
in parallel on a single judgehost machine. This might be useful on multi-core
machines.

Copyright and licencing
-----------------------

DOMjudge is developed by Jaap Eldering, Nicky Gerritsen, Keith Johnson,
Thijs Kinkhorst and Tobias Werth; Peter van de Werken has retired as developer.
Many other people have contributed:
Michael Baer,
Jeroen Bransen,
Matt Claycomb,
Stijn van Drongelen,
Rob Franken,
Marc Furon,
Ragnar Groot Koerkamp,
Matt Hermes,
Michał Kaczanowicz,
Jacob Kleerekoper,
Jason Klein,
Andreas Kohn,
Ruud Koot,
Ilya Kornakov,
Jan Kuipers,
Robin Lee,
Tom Levy,
Richard Lobb,
Alex Muntada,
Dominik Paulus,
Bert Peters,
Mart Pluijmaekers,
Ludo Pulles,
Tobias Polzer,
Jeroen Schot,
Matt Steele,
Shuhei Takahashi,
Michael Vasseur,
Sergei Vorobev,
Hoai-Thu Vuong,
Jeroen van Wolffelaar,
and Github users mpsijm, sylxjtu.
Some code has been ported from the ETH Zurich fork by Christoph
Krautz, Thomas Rast et al.

DOMjudge is Copyright (c) 2004 - |today| by the DOMjudge developers and contributors.

DOMjudge, including its documentation, is free software; you can redistribute
it and/or modify it under the terms of the GNU General Public License as
published by the Free Software Foundation; either version 2, or (at your
option) any later version. See the file COPYING for details.

This software is partly based on code by other people. Please refer to
individual files for acknowledgements.

About the name and logo
-----------------------

.. image:: ../logos/DOMjudgelogo.*
   :width: 100 px
   :alt: DOMjudge logo
   :align: right

The name of this judging system is inspired by a very important and well known
landmark in the city of Utrecht: the Dom tower.  The logo of the 2004 Dutch
Programming Championships (for which this system was originally developed)
depicts a representation of the Dom in zeros and ones. We based the name and
logo of DOMjudge on that.

We would like to thank Erik van Sebille, the original creator of the logo. The
logo is under a GPL licence, although Erik first suggested a "free as in beer"
licence first: you're allowed to use it, but you owe Erik a free beer in case
might you encounter him.

Contact
-------

The DOMjudge homepage can be found at: https://www.domjudge.org/

We have a low volume `mailing list for announcements
<https://www.domjudge.org/mailman/listinfo/domjudge-announce>`_
of new releases.
The authors can be reached through the development mailing list.
You need to be subscribed before you can post. See the
`development list information page 
<https://www.domjudge.org/mailman/listinfo/domjudge-devel>`_
for subscription and more details.

DOMjudge has a `Slack workspace <https://www.domjudge.org/chat>`_
where a number of developers and users of
DOMjudge linger. Feel free to drop by with your questions and comments,
but note that it may sometimes take a bit longer than a few minutes to
get a response, partly because people might be in different timezones.
