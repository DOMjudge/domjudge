Running the contest
===================

Team status
-----------
Under the Teams menu option, you can get a general impression of the
status of each team: a traffic light will show either of the
following:

- gray: the team has not (yet) connected to the web interface at all;
- red: he team has connected but not submitted anything yet;
- yellow: one or more submissions have been made, but none correct;
- green: the team has made at least one submission that has
  been judged as correct.

This is especially useful during the practice session, where it is
expected that every team can make at least one correct submission. A
team with any other colour than green near the end of the session
might be having difficulties.

.. _clarifications:

Clarification Requests
----------------------
Communication between teams and jury happens through Clarification
Requests. Everything related to that is handled under the
Clarifications menu item.

Teams can use their web interface to send a clarification request to
the jury. The jury can send a response to that team specifically, or
send it to all teams. The latter is done to ensure that all teams have
the same information about the problem set. The jury can also send a
clarification that does not correspond to a specific request. These
will be termed *general clarification*.

Handling clarification requests
```````````````````````````````
Under Clarifications, three lists are shown: new clarifications,
answered clarifications and general clarifications. Click the excerpt
for details about that clarification request.

Every incoming clarification request will initially be marked as
unanswered. The menu bar shows the number of unanswered requests. A
request will be marked as answered when a response has been sent.
Additionally it's possible to mark a clarification request as answered
with the button that can be found when viewing the request. The latter
can be used when the request has been dealt with in some other way,
for example by sending a general message to all teams.

An answer to a clarification request is made by putting the text in the
input box under the request text. The original text is quoted. You can
choose to either send it to the team that requested the clarification,
or to all teams. In the latter case, make sure you phrase it in such a
way that the message is self-contained (e.g. by keeping the quoted
text), since the other teams cannot view the original request.

In the DOMjudge configuration under ``clar_answers`` you can set predefined
clarification responses that can be selected when processing incoming
clarifications.

Clarification categories and queues
```````````````````````````````````
When sending a clarification request, the team needs to select an
appropriate *category* (or *subject*). DOMjudge will generate a category
for every problem in every active contest. You can define additional
categories in the DOMjudge configuration under ``clar_categories``.

Categories are hence visible to the teams and they need to select one.
In addition to this there's the concept of *queues*. Queues are purely
internal to the jury, not visible to the outside world and can be used
for internal workload assignment. In the DOMjudge configuration you can
define in ``clar_queues`` the available queues and a
``clar_default_problem_queue`` where newly created requests will end up in.
On the clarification overview page, you can quickly assign incoming
clarifications to a queue by pressing the queue's button in the table row.

.. _balloons:

Balloon handling
----------------
According to ICPC tradition, every solved problem earns the team a
balloon in colour specific to that problem. This can be facilitated
with the balloons interface reachable from the main menu.

The interface can be accessed by administrators and any user with
the *Balloon runner* role. It's possible to assign a user only this
role; they will be able to access the jury interface but only see
and handle balloons. You need to enable balloons processing for a
contest in the configuration under the Contests menu option.

For each first correct submission of a problem by a team, an entry
is created in the table on the balloons interface. A runner can then
be dispatched to hand out the inflatable award. The entry can be
marked as 'done' by clicking the running person at the end of the row.

Each row will also list the total amount of balloons that team has
earned so the runner can compare the resulting situation at the
team's workplace with the expected one. Where applicable there's
also an indication of whether this balloon is the first in the entire
contest, or the first for that problem to be handed out, should
you wish to do something special with these cases.

Normally balloon distribution stops when the scoreboard is frozen.
This will be indicated in the interface and no new entries will
show for submissions after the freeze. It is possibe that new
entries appear for some times after the freeze, if the result of
a submission before the freeze is only known after (this can also
happen in case of a :ref:`rejudging`).
The global configuration option ``show_balloons_postfreeze`` will
ignore a contest freeze for purposes of balloons and new correct
submissions will trigger a balloon entry in the table.
