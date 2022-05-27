Adding contest data in bulk
===========================

DOMjudge offers two ways to add or update contest data in bulk: using API
endpoints and using the jury interface.
In general, we follow the `CCS specification`_ for all file formats involved.

For using the API, the following examples require you to set up admin credentials
in your `.netrc`_ file. You need to install `httpie`_ and replace the
``<API_URL>`` in the examples below with the API URL of your local DOMjudge
installation.

Importing team categories
-------------------------

There are two formats to import team categories: a JSON format and a legacy TSV format.

Using JSON
^^^^^^^^^^

Prepare a file called ``groups.json`` which contains the team categories.
It should be a JSON array with objects, each object should contain the following
fields:

- ``id``: the category ID to use. Must be unique
- ``icpc_id`` (optional): an external ID, e.g. from the ICPC CMS, may be empty
- ``name``: the name of the team category as shown on the scoreboard
- ``hidden`` (defaults to ``false``): if ``true``, teams in this category will
  not be shown on the scoreboard
- ``sortorder`` (defaults to ``0``): the sort order of the team category to use
  on the scoreboard. Categories with the same sortorder will be grouped together.

Example ``groups.json``::

  [{
    "id": "13337",
    "icpc_id": "123",
    "name": "Companies",
    "hidden": true
  }, {
    "id": "47",
    "name": "Participants"
  }, {
    "id": "23",
    "name": "Spectators"
  }]

To import the file using the jury interface, go to `Import / export`, select
`groups` under `Import JSON / YAML`, select your file and click `Import`.

To import the file using the API run the following command::

    http --check-status -b -f POST "<API_URL>/users/groups" json@groups.json

Using the legacy TSV format
^^^^^^^^^^^^^^^^^^^^^^^^^^^

Prepare a file called ``groups.tsv`` which contains the team categories.
The first line should contain ``File_Version 1`` (tab-separated).
Each of the following lines must contain the following elements separated by tabs:

- the category ID. Must be unique
- the name of the team category as shown on the scoreboard

Example ``groups.tsv``::

   File_Version   1
   13337	Companies
   47	Participants
   23	Spectators

To import the file using the jury interface, go to `Import / export`, select
`groups` under `Tab-separated import`, select your file and click `Import`.

To import the file using the API run the following command::

    http --check-status -b -f POST "<API_URL>/users/groups" tsv@groups.tsv

Importing team affiliations
---------------------------

.. note::

    The team TSV import automatically imports team affiliations as well.

Prepare a file called ``organizations.json`` which contains the affiliations.
It should be a JSON array with objects, each object should contain the following
fields:

- ``id``: the external affiliation ID. Must be unique
- ``icpc_id`` (optional): an external ID, e.g. from the ICPC CMS, may be empty
- ``name``: the affiliation short name as used in the jury interface and certain
  exports
- ``formal_name``: the affiliation name as used on the scoreboard
- ``country``: the country code in form of ISO 3166-1 alpha-3

Example ``organizations.json``::

  [{
    "id": "INST-42",
    "icpc_id": "42",
    "name": "LU",
    "formal_name": "Lund University",
    "country": "SWE"
  }, {
    "id": "INST-43",
    "icpc_id": "43",
    "name": "FAU",
    "formal_name": "Friedrich-Alexander-University Erlangen-Nuremberg",
    "country": "DEU"
  }]

To import the file using the jury interface, go to `Import / export`, select
`organizations` under `Import JSON / YAML`, select your file and click `Import`.

To import the file using the API run the following command::

    http --check-status -b -f POST "<API_URL>/users/organizations" json@organizations.json

Importing teams
---------------

There are two formats to import teams: a JSON format and a legacy TSV format.

Using JSON
^^^^^^^^^^

Prepare a file called ``teams.json`` which contains the teams.
It should be a JSON array with objects, each object should contain the following
fields:

- ``id``: the team ID. Must be unique
- ``icpc_id`` (optional): an external ID, e.g. from the ICPC CMS, may be empty
- ``group_ids``: an array with one element: the category ID this team belongs to
- ``name``: the team name as used in the web interface
- ``members`` (optional): Members of the team as one long string
- ``display_name`` (optional): the team display name. If provided, will display
  this instead of the team name in certain places, like the scoreboard
- ``organization_id``: the external ID of the team affiliation this team belongs to

Example ``teams.json``::

  [{
    "id": "1",
    "icpc_id": "447047",
    "group_ids": ["24"],
    "name": "¡i¡i¡",
    "organization_id": "INST-42"
  }, {
    "id": "2",
    "icpc_id": "447837",
    "group_ids": ["25"],
    "name": "Pleading not FAUlty",
    "organization_id": "INST-43"
  }]

To import the file using the jury interface, go to `Import / export`, select
`teams` under `Import JSON / YAML`, select your file and click `Import`.

To import the file using the API run the following command::

    http --check-status -b -f POST "<API_URL>/users/teams" json@teams.json

Using the legacy TSV format
^^^^^^^^^^^^^^^^^^^^^^^^^^^

Prepare a file called ``teams2.tsv`` which contains the teams.
The first line should contain ``File_Version	2`` (tab-separated).
Each of the following lines must contain the following elements separated by tabs:

- the team ID. Must be unique
- an external ID, e.g. from the ICPC CMS, may be empty
- the category ID this team belongs to
- the team name as used in the web interface
- the institution name as used on the scoreboard
- the institution short name as used in the jury interface and certain exports
- a country code in form of ISO 3166-1 alpha-3
- an external institution ID, e.g. from the ICPC CMS, may be empty

Example ``teams2.tsv``::

   File_Version   2
   1	447047	24	¡i¡i¡	Lund University	LU	SWE	INST-42
   2	447837	25	Pleading not FAUlty	Friedrich-Alexander-University Erlangen-Nuremberg	FAU	DEU	INST-43


To import the file using the jury interface, go to `Import / export`, select
`teams` under `Tab-separated import`, select your file and click `Import`.

To import the file using the API run the following command::

    http --check-status -b -f POST "<API_URL>/users/teams" tsv@teams2.tsv

Importing accounts
------------------

There are two formats to import accounts: a YAML format and a legacy TSV format.

Using YAML
^^^^^^^^^^

Prepare a file called ``accounts.yaml`` which contains the accounts.
It should be a YAML array with objects, each object should contain the following
fields:

- ``id``: the account ID. Must be unique
- ``username``: the account username. Must be unique
- ``password``: the password to use for the account
- ``type``: the user type, one of ``team``, ``judge``, ``admin`` or ``balloon``, ``jury`` will be interpret as ``judge``
- ``team_id``: (optional) the external ID of the team this account belongs to
- ``name``: (optional) the full name of the account
- ``ip`` (optional): IP address to link to this account

Example ``accounts.yaml``::

   - id: team001
     username: team001
     password: P3xm33imve
     type: team
     name: team001
     ip: 10.10.2.1

   - id: team002
     username: team002
     password: qd4WHeJXbd
     type: team
     name: team002
     ip: 10.10.2.2

   - id: john
     username: john
     password: Uf4PYRA7mJ
     type: judge
     name: John Doe

.. note::

    You can also use a JSON file instead of YAML. Make sure to name it
    ``accounts.json`` in that case.

To import the file using the jury interface, go to `Import / export`, select
`accounts` under `Import JSON / YAML`, select your file and click `Import`.

To import the file using the API run the following command::

    http --check-status -b -f POST "<API_URL>/users/accounts" yaml@accounts.yaml

Using the legacy TSV format
^^^^^^^^^^^^^^^^^^^^^^^^^^^

Prepare a file called ``accounts.tsv`` which contains the team credentials.
The first line should contain ``accounts  1`` (tab-separated).
Each of the following lines must contain the following elements separated by tabs:

- the user type, one of ``team``, ``judge``, ``admin`` or ``balloon``, ``jury`` will be interpret as ``judge``
- the full name of the user
- the username
- the password
- (optional) the IP address to the user

Example ``accounts.tsv``::

   accounts	1
   team	team001	team001	P3xm33imve	10.10.2.1
   team	team002	team002	qd4WHeJXbd	10.10.2.2
   judge	John Doe	john	Uf4PYRA7mJ

To import the file using the jury interface, go to `Import / export`, select
`accounts` under `Tab-separated import`, select your file and click `Import`.

To import the file using the API run the following command::

    http --check-status -b -f POST "<API_URL>/users/accounts" tsv@accounts.tsv

Importing contest metadata
--------------------------

Prepare a file called ``contest.yaml`` which contains the contest information.

Example ``contest.yaml``::

   id:                         practice
   name:                       DOMjudge open practice session
   start_time:                 2020-04-30T10:00:00+01:00
   duration:                   2:00:00
   scoreboard_freeze_duration: 0:30:00
   penalty_time:               20

.. note::

    You can also use a JSON file instead of YAML. Make sure to name it
    ``contest.json`` in that case.

To import the file using the jury interface, go to `Import / export`, then
`Contest -> Import JSON / YAML`, select your file under `File`
and click `Import`.

To import the file using the API run the following commands::

    http --check-status -b -f POST "<API_URL>/contests" yaml@contest.yaml

This call returns the new contest ID, which you need to import problems.

Importing problem metadata
--------------------------

Prepare a file called ``problems.yaml`` which contains the problemset information.

Example ``problems.yaml``::

  - id:     hello
    label:  A
    name:   Hello World
    color:  Orange
    rgb:    '#FF7109'

  - id:     boolfind
    label:  B
    name:   Boolfind
    color:  Forest Green
    rgb:    '#008100'

.. note::

    You can also use a JSON file instead of YAML. Make sure to name it
    ``problems.json`` in that case.

To import the file using the jury interface, go to `Import / export`, then
`Problems -> Import JSON / YAML`, select your file under `File`
and click `Import`.

To import the file using the API run the following commands::

    http --check-status -b -f POST "<API_URL>/contests/<CID>/problems" data@problems.yaml


Replace ``<CID>`` with the contest ID that was returned when importing the
contest metadata.

Importing problems
------------------

Prepare your problems in the :doc:`ICPC problem format <problem-format>` and
create a ZIP file for each problem.

To import the file using the jury interface, go to `Problems`, select the contest
you want to import the problems into, select your file under `Problem archive(s)`
and click `Upload`.

To import the file using the API run the following command::

    http --check-status -b -f POST "<API_URL>/contests/<CID>/problems" zip@problem.zip problem="<PROBID>"

Replace ``<CID>`` with the contest ID that the previous command returns and
``<PROBID>`` with the problem ID (you can get that from the web interface or
the API).

Putting all API imports together
--------------------------------

If you prepare your contest configuration as we described in the previous
subsections, you can also use the script that we provide in
`misc-tools/import-contest.sh`.

Call it from your contest folder like this::

    misc-tools/import-contest.sh <API_URL>

Importing from ICPC CMS API
---------------------------

DOMjudge also offers a direct import/refresh of teams from the ICPC CMS API from
within the DOMjudge web interface. You need a  a team category named 'Participants'
where they will be placed and a ICPC Web Services Token.

To create a Web Services Token, log into the ICPC CMS and click "Export > Web
Services Tokens". Make sure you add the scopes "Export", "Standings Upload",
and "MyICPC".  Under the `Import / Export` menu, enter the token as specified.
Use the contest abbreviation and year as Contest ID (see the URL in the ICPC
CMS).

Based on the 'ICPC ID', teams and their affilations will be added if they do not
exist or updated when they do. Teams will be set to 'enabled' if their ICPC CMS
status is 'ACCEPTED', of disabled otherwise. Affilations are not updated or
deleted even when all teams cancel.

.. _CCS specification: https://ccs-specs.icpc.io/2021-11/ccs_system_requirements#appendix-file-formats
.. _.netrc: https://www.gnu.org/software/inetutils/manual/html_node/The-_002enetrc-file.html
.. _httpie: https://httpie.org/
