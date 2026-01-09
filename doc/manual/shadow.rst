Appendix: Running DOMjudge as a shadow system
=============================================

It is possible to run DOMjudge as a shadow system behind another system.

Shadowing means that DOMjudge will read events from an external system and mimic
all actions described in those events. This is useful if one wants to verify if
the results of an external system match DOMjudge, for example to verify both
systems give the same judging results. This has been used at the ICPC World
Finals for the last few years.

DOMjudge can shadow any system that follows the `Contest API specification`_.
It has been tested with recent versions of the `ICPC Tools CDS`_
and with DOMjudge itself. Other known systems that implement the specification
and that should work are `Kattis`_ and `PC-Squared`_.

Configuring DOMjudge
--------------------

Shadow mode is configured per contest. In the DOMjudge admin interface, go to
the contest edit page and configure the shadow mode settings:

* **Enable shadow mode**: Set this to *Yes* to enable shadow mode for this contest.
  This will:

  * Add a *Shadow Differences* item to the jury menu and homepage for admins.
  * Expose additional information in the submission overview and detail pages.

* **Use external judgements**: When set to *Yes*, external judgements will be used
  for results and scoring instead of local judgings. This also affects the API
  responses for judgements and runs endpoints.

* **External source type**: Select *CCS API (URL)* to shadow from a Contest API
  endpoint, or *Contest package (directory)* to shadow from a local directory
  containing a CLICS-compliant contest archive.

* **External source**: For CCS API, enter the URL to the contest in the external
  system's API (e.g., ``https://example.com/api/contests/wf2024``). For contest
  package, enter the path to the directory on disk.

* **External source username/password**: For CCS API sources, enter the credentials
  to authenticate with the external system.

You can also set the *external_ccs_submission_url* configuration setting to a URL
template. If you set this, the submission detail page will show a link to the
external system. If you use DOMjudge as the system to shadow from, you should enter
``https://url.to.domjudge/jury/submissions/[id]``.

Importing or creating the contest and problems
----------------------------------------------

The contest needs to exist in DOMjudge *and* have all problems loaded. All other
configuration data, like teams, team categories and team affiliations should also
exist. The easiest way to create the contest and configuration data is to import
it. For this you need JSON files as described in the :doc:`Import <import>` chapter.
Furthermore you need problem directories as described on that same page. Place
all the files and all directories together in one directory and use the
``misc-tools/import-contest`` script to import them. Note that, if you have no
team linked to your account, it will complain that it can't import jury
submissions because you have no team linked to your account. That is fine since
these submissions will be read using the event feed later on.

You can also configure shadow mode settings via the ``contest.yaml`` file using
these DOMjudge-specific keys::

  shadow:
    use_judgements: true
    type: ccs-api
    source: http(s)://url_to_external_system/api/contests/<contest_id>
    username: user_in_external_system
    password: password_for_user

or::

  shadow:
    use_judgements: false
    type: contest-archive
    source: /path/on/domjudge/filesystem/to/clics/compliant/archive

Running the event feed import command
-------------------------------------

After doing all above steps, you can run the event feed import command to start
importing events from the primary system. The external contest source detail
page will show you the exact command to run.

If the command loses its connection to the primary system it will automatically
reconnect and continue reading where it left off. To stop the command, press
``ctrl-c``. If you rerun the command, it will start again where it left off.

If you ever want to restart reading the feed from the beginning (for example
when the primary system had an error and fixed that), you can pass
``--from-start`` to the command to start reading from the beginning.

Viewing import warnings
-----------------------

During import a lot can happen. For example, an external system can send an event
that contains an unknown dependency, it can send an event we can't process or we
might not be able to download a submission ZIP.

The external contest source detail page will show a list of all warnings that
occurred during import. If an event that is imported later fixes a warning, the
warning will automatically disappear.

The detail page also shows when the feed reader last checked in, what the last event
was that it processed and when it processed that event. This might be useful information
to keep track of while shadowing.

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

For submissions with a difference between the primary and shadow system,
you can mark a difference as verified on this page. This will make it not show
up when only showing *Verified* submissions. The menu badge will also not take
them into account.

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

.. _Contest API specification: https://ccs-specs.icpc.io/2021-11/contest_api
.. _ICPC Tools CDS: https://tools.icpc.global/cds
.. _Kattis: https://www.kattis.com
.. _PC-Squared: http://pc2.ecs.csus.edu
.. _CCS requirements specification: https://ccs-specs.icpc.io/2021-11/ccs_system_requirements
