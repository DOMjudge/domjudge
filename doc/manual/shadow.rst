Appendix: Running DOMjudge as a shadow system
=============================================

It is possible to run DOMjudge as a shadow system behind another system.

Shadowing means that DOMjudge will read events from an external system and mimic
all actions described in those events. This is useful if one wants to verify if
the results of an external system match DOMjudge, for example to verify both
systems give the same judging results. This has been used at the ICPC World
Finals for the last few years.

DOMjudge can shadow any system that follows the
`Contest API specification
<https://clics.ecs.baylor.edu/index.php?title=Contest_API>`_. It has been tested
with recent versions of the `ICPC Tools CDS <https://tools.icpc.global/cds>`_
and with DOMjudge itself. Other known systems that implement the specification
and that should work are `Kattis <https://www.kattis.com>`_ and
`PC-Squared <http://pc2.ecs.csus.edu>`_.

Configuring DOMjudge
--------------------

In the DOMjudge admin interface, go to *Configuration settings* page and modify
the settings to mimic the system to shadow from. Also make sure to set
*data_source* to ``configuration and live data external``. This tells DOMjudge
that it will be a shadow for an external system. This will:

* Expose external ID's in the API for both configuration and live data, i.e.
  problems, teams, etc. as well as submissions, judgings and runs.
* Add a *Shadow Differences* item to the jury menu and homepage for admins.
* Expose additional information in the submission overview and detail pages.

You can also set the *external_ccs_submission_url* to a URL template. If you set
this, the submission detail page will show a link to the external system. If you
use DOMjudge as system to shadow from, you should enter
``https://url.to.domjudge/jury/submissions/[id]``.

Importing or creating the contest and problems
----------------------------------------------

The contest needs to exist in DOMjudge *and* have all problems loaded. It is
best to not have any other data for the contest like teams, team categories,
team affiliations, submissions and clarifications. The easiest way to create the
contest is to import it. For this you need a ``contest.yaml`` and a
``problemset.yaml`` as described in the `ICPC CCS requirements page
<https://clics.ecs.baylor.edu/index.php?title=Contest_Control_System_Requirements>`_.
Furthermore you need problem directories as described on the
:doc:`problem format <problem-format>` page. Place the two YAML files and all
directories together in one directory and use the
``misc-tools/import-contest.sh`` script to import them. Do not try to import
groups, teams or accounts using it, but do import all other things. Note that
it will complain that no team is associated with your account so it can't import
jury submissions. That is fine since these submissions will be read using the
event feed later on.

Creating the shadowing config file
----------------------------------

On the DOMserver, copy the file ``etc/shadowing.yaml.dist`` to
``/etc/shadowing.yaml`` and modify its contents. The ``.dist`` file contains
comments as to what each field in the file means, but to summarize:

- As the key pick a recognizable identifier, for example the ID of the contest
  in the external system.
- Set ``id`` to the (database) ID of the contest in DOMjudge that you created.
- Set the ``feed-url``, ``username`` and ``password`` fields to the correct data
  as provided by the primary system you will be shadowing.
- Check if you need any of the other options like ``allow-insecure-ssl``. All
  other options should only be used if you know what you are doing.

Running the event feed import command
-------------------------------------

After doing all above steps, you can run the event feed import command to start
importing events from the primary system. From the DOMserver installation
directory, run::

  import/import-event-feed key-from-yaml

Where ``key-from-yaml`` is the key you configured in the previous step. The
command will start running and import events.

If the command loses its connection to the primary system it will automatically
reconnect and continue reading where it left off. To stop the command, press
``ctrl-c``. If you rerun the command, it will start again where it left off.

If you ever want to restart reading the feed from the beginning (for example
when the primary system had an error and fixed that), you can pass
``--from-start`` to the command to start reading from the beginning. Note that
the way DOMjudge keeps track of the events that it already processed is by
keeping a copy of the event feed in ``TMPDIR`` as defined in
``/etc/domserver-static.php``. This feed is named after the key you configured
in the YAML file, so if you ever reuse the key, make sure to remove the cached
file or pass ``--from-start``.

Viewing shadow differences
--------------------------

In the admin interface, the *Shadow Differences* page will show all differences
between the primary system and DOMjudge. Note that it will only show differences
in the final verdict, not on a testcase level.

At the top is a matrix where a summary of the differences is shown. All red
cells are actual differences. If they appear in the *JU* column or row, this
simply means one of the systems is not done yet with judging this submission.

Clicking any of the red cells will show detailed information about the
differences that correspond to it. You can also use the dropdowns in the
*Details* section. Clicking on a submission will bring you to the submission
detail page.

The submission details page shows more information related to the shadow
differences:

* If you set the *external_ccs_submission_url* configuration option, a link
  titled *View in external CCS* will appear taking you to the submission in the
  primary system. Clicking the external ID just below it will do the same.
* The external ID and judgement (if any) will be shown.
* A graph of external testcase runtimes is displayed.
* The external testcase results are shown, but in a summary line as well as
  with detailed information.
* If the primary system has a different verdict than DOMjudge, a warning will be
  displayed.
