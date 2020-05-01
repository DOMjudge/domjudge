Adding contest data programmatically
====================================

DOMjudge offers API endpoints to add or update contest data programmatically.
In general, we try to follow the `CLICS CCS specification
<https://clics.ecs.baylor.edu/index.php?title=Contest_Control_System_Requirements>`_
for all file formats involved.

All of the following examples require you to set up admin credentials in your
`.netrc <https://www.gnu.org/software/inetutils/manual/html_node/The-_002enetrc-file.html>`_ file.
You need to install `httpie <https://httpie.org/>`_ and replace the
``<API_URL>`` in the examples below with the API URL of your local DOMjudge
installation.

Importing team categories
-------------------------

Prepare a file called ``groups.tsv`` which contains the team categories.
The first line should contain ``File_Version 1`` (tab-separated).
Each of the following lines must contain the following elements separated by tabs:

- the category ID
- the name of the team category

Example ``groups.tsv``::

   File_Version   1
   13337	Companies
   47	Netherlands
   23	United Kingdom

To import the file run the following command::

    http --check-status -b -f POST "<API_URL>/users/groups" tsv@groups.tsv

Importing teams
---------------

Prepare a file called ``teams2.tsv`` which contains the teams.
The first line should contain ``File_Version	2`` (tab-separated).
Each of the following lines must contain the following elements separated by tabs:

- the team ID
- an external ID, e.g. from the ICPC CMS, may be empty
- the category ID
- the team name
- the institution name
- the institution short name
- a country code in form of ISO 3166-1 alpha-3
- an external institution ID, e.g. from the ICPC CMS, may be empty

Example ``teams2.tsv``::

   File_Version   2
   1	447047	24	¡i¡i¡	Lund University	LU	SWE	INST-42
   2	447837	25	Pleading not FAUlty	Friedrich-Alexander-University Erlangen-Nuremberg	FAU	DEU	INST-43


To import the file run the following command::

    http --check-status -b -f POST "<API_URL>/users/teams" tsv@teams2.tsv

Importing accounts
------------------

Prepare a file called ``accounts.tsv`` which contains the team credentials.
The first line should contain ``accounts  1`` (tab-separated).
Each of the following lines must contain the following elements separated by tabs:

- the user type, one of ``team`` or ``judge``
- the full name of the user
- the username
- the password

Example ``accounts.tsv``::

   accounts	1
   team	team001	team001	P3xm33imve
   team	team002	team002	qd4WHeJXbd
   judge	John Doe	john	Uf4PYRA7mJ

To import the file run the following command::

    http --check-status -b -f POST "<API_URL>/users/accounts" tsv@accounts.tsv

Importing contest metadata
--------------------------

Prepare a file called ``contest.yaml`` which contains the contest information and a file called ``problemset.yaml`` which contains the problemset information.

Example ``contest.yaml``::

   name:                     DOMjudge open practice session
   short-name:               practice
   start-time:               2020-04-30T10:00:00+01:00
   duration:                 2:00:00
   scoreboard-freeze-length: 0:30:00
   penalty-time:             20

Example ``problemset.yaml``::

   problems:
     - letter:     A
       short-name: hello
       color:      Orange
       rgb:        '#FF7109'
   
     - letter:     B
       short-name: boolfind
       color:      Forest Green
       rgb:        '#008100'

Concatenate both YAML files into one and then import the combined file by
running the following command::

    http --check-status -b -f POST "<API_URL>/contests" yaml@combined.yaml

This call returns the new contest ID.

Importing problems
------------------

Prepare your problems in the :doc:`ICPC problem format <problem-format>` and
create a ZIP file for each problem and upload it by running the following
command::

    http --check-status -b -f POST "<API_URL>/contests/<CID>/problems" zip[]@problem.zip problem="<PROBID>"

Replace ``<CID>`` with the contest ID that the previous command returns and
``<PROBID>`` with the problem ID (you can get that from the web interface or
the API).

Putting it all together
-----------------------

If you prepare your contest configuration as we described in the previous
subsections, you can also use the script that we provide in
`misc-tools/import-contest.sh`.

Call it from your contest folder like this::

    misc-tools/import-contest.sh <API_URL>

