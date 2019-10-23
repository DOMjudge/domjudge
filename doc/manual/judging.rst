Judging topics
==============

Flow of a submission
--------------------
The flow of an incoming submission is as follows.

#. Team submits solution. It will either be rejected after basic
   checks, or accepted and stored as a *submission*.
#. The first available *judgehost* compiles, runs and checks
   the submission. The outcome and outputs are stored as a
   *judging* of this submission. Note that judgehosts may be
   restricted to certain contests, languages and problems, so that it can be
   the case that a judgehost is available, but not judging an available
   submission.
#. If verification is not required, the result is automatically
   recorded and the team can view the result and the scoreboard is
   updated (unless after the scoreboard freeze). A judge can
   optionally inspect the submission and judging and mark it
   verified.
#. If verification is required, a judge inspects the judging. Only
   after it has been approved (marked as *verified*) will
   the result be visible outside the jury interface. This option
   can be enabled by setting ``verification_required`` on
   the *configuration settings* admin page.

.. _rejudging:

Rejudging
---------
In some situations it is necessary to rejudge one or more submissions. This means
that the submission will re-enter the flow as if it had not been
judged before. The submittime will be the original time, but the
program will be compiled, run and tested again.

This can be useful when there was some kind of problem: a compiler
that was broken and later fixed, or testdata that was incorrect and
later changed. When a submission is rejudged, the old judging data is
kept but marked as *invalid*.

You can rejudge a single submission by pressing the 'Rejudge' button
when viewing the submission details. It is also possible to rejudge
all submissions for a given language, problem, team or judgehost; to
do so, go to the page of the respective language, problem, team or
judgehost, press the 'Rejudge all' button and confirm.

There are two different ways to run a rejudging, depending on whether
the *create rejudging* button is enabled:

- Without this button toggled, an instant rejudging is
  performed where the results are directly made effective.
- When toggled, a "rejudging" set is created, and all affected
  submissions are rejudged, but the new judgings are not made
  effective yet. Instead, the jury can inspect the results of the
  rejudging (under the rejudging tab). Based on that the whole
  rejudging, as a set, can be applied or cancelled, keeping the old
  judgings as is.

Submissions that have been marked as 'CORRECT' will not be rejudged.
Only DOMjudge admins can override this restriction using a tickbox.

Teams will not get explicit notifications of rejudgings, other than a
potentially changed outcome of their submissions. It might be desirable
to combine rejudging with a clarification to the team or all teams
explaining what has been rejudged and why.

Ignoring a submission
---------------------
There is the option to *ignore* specific submissions
using the button on the submission page. When a submission is being
ignored, it is as if was never submitted: it will show strike-through
in the jury's and affected team's submission list, and it is not
visible on the scoreboard. This can be used to effectively
delete a submission for some reason, e.g. when a team erroneously sent
it for the wrong problem. The submission can also be unignored again.

Enforcement of time limits
--------------------------
Time limits within DOMjudge are enforced primarily in CPU time, and
secondly a more lax wall clock time limit is used to make sure that
submissions cannot idle and hog judgedaemons. The way that time limits
are calculated and passed through the system involves a number of
steps.

Time limits are set per problem in seconds. Each language in turn may
define a time factor (defaulting to 1) that multiplies it to get a
specific time limit for that problem/language combination. This is
the 'soft timelimit'. The configuration setting `timelimit
overshoot` is then used to calculate a 'hard timelimit'.
This overshoot can be specified in terms of an absolute and relative
margin.

The `soft:hard` timelimit pair is passed to `runguard` as both
wall clock and CPU limit. This is used by `runguard` when reporting
whether the soft, actual timelimit has been surpassed. The submitted
program gets killed when either the hard wall clock or CPU time has passed.

.. _judging-consistency:

Judging consistency
-------------------
The following issues can be considered to improve consistency in
judging.

Disable CPU frequency scaling and Intel "Turbo Boost" to
prevent fluctuations in CPU power.

Disable address-space randomization to make programs with
memory addressing bugs give more reproducible results. To
do that, you can add the following line to ``/etc/sysctl.conf``::

  kernel.randomize_va_space=0

Then run the following command::

  sudo sysctl -p

to directly activate this setting.

Lazy judging and results priority
---------------------------------
In order to increase capacity, you can set the DOMjudge configuration option
``lazy_eval_results``. When enabled, judging of a submission will stop when
a highest priority result has been found for any testcase. You can find these
priorities under the ``results_prio`` setting. In the default configuration,
when enabling this, judging will stop with said verdict when a testcase
results in e.g. *run-error*, *timelimit* or *wrong-answer*. When a testcase
is *correct* (lower priority), judging will continue to the next test case.
In other words, to arrive at a verdict of *correct*, all testcases will have
been evaluated, while any of the 'error' verdicts will immediately return this
answer for the submission and the other testcases will never be tested, since
the submission can never become correct anymore if one has failed.

Since many of the submissions are expected to have some kind of error, this
will significantly save on judging time.

When not using lazy judging, all testcases will always be ran for each
submission. The ``results_prio`` list will then determine which of the
individual testcase results will be the overall submission result:
the highest priority one. In case of a tie, the first occurring testcase
result with highest priority is returned.

Judgehost restrictions
----------------------
It is possible to dedicate certain judgehosts only for certain languages,
problems or contests; or a combination thereof. To set this up, configure
the desired restiction pattern under *Judgehost restrictions* from the
main menu. For example, you select contest 1 and language Java.
Then, you can edit all judgehosts and apply the newly created restriction
to any of them. The judgehosts with this restriction will only pick up
submissions that are in contest 1 *and* are submitted in Java. Submissions
for other languages, or in other contests, will need to be processed by
other judgehosts.

When adding restrictions, take care that there must remain judgehosts
available to judge every active problem, language and contest.
The *Configuration checker* will perform a check for this.

A special restriction is turning off *Allow rejudge on same judgehost*.
This defaults to Yes (so a rejudge of a submission can happen on any
judgehost), but you can add a judgehost restriction with this setting
to No. This can be used to test timings on judgehosts by configuring
all judgehosts with this restriction and then rejudging a set of submissions
as many times as there are judgehosts. This will lead to the situation that
each judgehosts has judged every submission exactly once.

Solutions to common issues
--------------------------

JVM and memory limits
`````````````````````
DOMjudge imposes memory limits on submitted solutions. These limits
are imposed before the compiled submissions are started. On the other
hand, the Java virtual machine is started via a compile-time generated
script which is run as a wrapper around the program. This means that
the memory limits imposed by DOMjudge are for the jvm and the running
program within it. As the jvm uses approximately 300MB, this reduces
the limit by this significant amount. See the `java_javac` and
`java_javac_detect` compile executable scripts for the
implementation details.

If you see error messages of the form::

  Error occurred during initialization of VM
  java.lang.OutOfMemoryError: unable to create new native thread

or::

  Error occurred during initialization of VM
  Could not reserve enough space for object heap

Then the problem is likely that the jvm needs more memory than what is
reserved by the Java compile script. You should try to increase the
`MEMRESERVED` variable in the java compile executable and check that
the configuration variable `memory limit` is set larger than
`MEMRESERVED`. If that does not help, you should try to increase the
configuration variable `process limit` (since the JVM uses a lot of
processes for garbage collection).

'runguard: root privileges not dropped'
```````````````````````````````````````
When this error occurs on submititng any source::

  Compiling failed with exitcode 255, compiler output:
  /home/domjudge/system/bin/runguard: root privileges not dropped

this indicates that you are running the `judgedaemon` as root user. You should
not run any part of DOMjudge as root; the parts that require it will gain root
by themselves through sudo. Either run it as yourself or, probably better,
create dedicated a user `domjudge` under which to install and run everything.

.. attention::

  Do not confuse this with the `domjudge-run` user:
  this is a special user to run submissions as and should also not
  be used to run normal DOMjudge processes; this user is only for
  internal use.

