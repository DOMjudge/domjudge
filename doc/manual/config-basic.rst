Configuring the system
======================

Configuration of the judge system is done by logging in as administrator
to the web interface.
The default username is ``admin`` with initial password stored in
``etc/initial_admin_password.secret``.

The general system settings can be accessed under
*Configuration settings*. Changes take effect immediately.

Setting up users and teams
--------------------------
Under *Users* from the homepage you can add user accounts for the
people accessing your system. There are several roles possible:

- Administrative user: can configure and change everything in DOMjudge.
- Jury user: can view submissions and judgings. Can view
  :ref:`clarification requests <clarifications>` and send clarifications.
  Can :ref:`rejudge <rejudging>` non-correct judgings (submissions judged
  *correct* can only be rejudged by an administrator).
- Balloon runner: can only view the :ref:`balloon queue <balloons>` and mark
  balloons as delivered.
- Team member: can view its own team interface and submit solutions
  (see below).
- Several system roles: they are for :ref:`API` access. The most important
  one is *judgehost* which means the account credentials can be used by a
  judgedaemon.

To set up teams, you can start in the *Teams* page and add teams there.
You then have the option to automatically create a corresponding user
account that is associated with the team.

It is also possible to use the *Import / Export* page to import
`ICPC-compatible tsv files
<https://clics.ecs.baylor.edu/index.php?title=Contest_Control_System_Requirements#teams.tsv>`_
with teams.

A jury or administrative user can also be associated with a team. This
will enable that user to submit solutions to the system, or resubmit
edited team solutions.

Resetting the password for a user
---------------------------------

If you do not have access anymore to any admin user, you can use the following
command to reset the password of a user to a random value::

  webapp/bin/console domjudge:reset-user-password admin

Replace ``admin`` with the username of the user you want to reset the password for.
The password will be displayed.

Adding a contest
----------------
You configure a new contest by adding it under the Contests link
from the main page.

Besides the name the most important configuration about a contest
are the various time milestones.

A contest can be selected for viewing after its *activation time*, but
the scoreboard will only become visible to public and teams once the
contest *starts*. Thus no data such as problems and teams is revealed
before then.

When the contest *ends*, the scores will remain displayed until the
*deactivation time* passes.

DOMjudge has the option to 'freeze' the public and team scoreboards
at some point during the contest. This means that scores are no longer
updated and remain to be displayed as they were at the time of the
freeze. This is often done to keep the last hour interesting for all.
The scoreboard freeze time can be set with the *freezetime* milestone.

The scoreboard freezing works by looking at the time a submission is
made. Therefore it's possible that submissions from (just) before the
freezetime but judged after it can still cause updates to the public
scoreboard. A rejudging during the freeze may also cause such updates.
The jury interface will however always show the actual
scoreboard.

Once the contest is over, the scores are not directly 'unfrozen'.
You can release the final scores to team and public interfaces when the
time is right. You can do this either by setting a predefined
*unfreezetime* in the contest table, or you push the 'unfreeze
now' button in the jury web interface, under contests.

All events happen at the first moment of the defined time. That is:
for a contest with starttime "12:00:00" and endtime "17:00:00", the
first submission will be accepted at 12:00:00 and the last one at
16:59:59.

Setting up problems
-------------------
When this is done, you can upload the intended
problems that teams need to solve under *Problems*. DOMjudge supports
uploading them as :doc:`a zip file <problem-format>` or configuring
each problem manually via the interface. You can add a problem to a
contest while uploading, or associate it by editing the contest
from the Contests page later.

It is possible to change whether teams can submit solutions for that
problem (using the toggle switch 'allow submit'). If disallowed,
submissions for that problem will be rejected, but more importantly,
teams will not see that problem on the scoreboard. Disallow judge
will make DOMjudge accept submissions, but leave them queued; this
is useful in case an unexpected problem shows up with one of the
problems. Timelimit is the maximum number of seconds a submission
for this problem is allowed to run before a 'TIMELIMIT' response
is given (to be multiplied possibly by a language factor). A
'timelimit overshoot' can be configured to let submissions run a
bit longer. Although DOMjudge will use the actual limit to
determine the verdict, this allows judges to see if a submission
is close to the timelimit.

Problems can have special *compare* and
*run* scripts associated to them, to deal with problem
statements that require non-standard evaluation.

Checking your configuration
---------------------------
From the front page the *Config checker* is available. This tool will
do a basic sanity check of your DOMjudge setup and gives helpful hints
to improve it. Be sure to run it when you've set up your contest.


Testing jury solutions
----------------------
Before a contest, you will want to have tested your reference
solutions on the system to see whether those are judged as expected
and maybe use their runtimes to set timelimits for the problems.

The simplest way to do this is to include the jury solutions in a
problem zip file and upload this. You can also upload a zip file
containing just solutions to an existing problem. The zip
archive has to adhere to the `ICPC problem format`_.
For this to work, the jury/admin user who uploads the problem has to
have an associated team to which the solutions will be assigned. The
solutions will automatically be judged if the contest is active (but
it need not have started yet). You can verify whether the submissions
gave the expected answer in the Judging Verifier, available from
the jury index page.

.. _ICPC problem format: https://clics.ecs.baylor.edu/index.php?title=Problem_format
