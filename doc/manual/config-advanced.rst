Advanced configuration topics
=============================

Adding graphics
---------------
DOMjudge can optionally present country flags, affiliation logos,
team pictures and a page-wide banner on the public interface.

You can place the images under the path `public/images/` as
follows:

- *Country flags* are shown when the ``show_flags`` configuration option
  is enabled. They are shipped with DOMjudge under
  `public/images/countries/XXX.png` with *XXX* being the country code.
  You can replace them if you want different flags.
- *Affiliation logos*: these will be shown with the teams that are
  part of the affilation, if the ``show_affiliation_logos`` configuration
  option is enabled. They can be placed in
  `public/images/affiliations/1234.png` where *1234* is the numeric ID
  of the affiliation as shown in the DOMjudge interface. There is a
  separate option ``show_affiliations`` that independently controls where
  the affiliation *names* are shown on the scoreboard.
- *Team pictures*: a photo of the team will be shown in the team details
  page if `public/images/teams/456.jpg` exists, where *456* is the
  team's numeric ID as shown in the DOMjudge interface.
- *Banner*: a page-wide banner can be shown on all pages of the public
  interface, if that image is placed in `public/images/banner.png`.

.. note::

  The IDs for affiliations and teams need to be the *external ID*
  if the ``data_source`` setting of DOMjudge is set to external.

Authentication and registration
-------------------------------
Out of the box users are able to authenticate using username and password.

Two other authentication methods are available:

- IP Address - authenticates users based on the IP address they are accessing
  the system from;
- X-Headers - authenticates users based on some HTTP header values.

There's an option to let teams register themselves in the system.

IP Address
``````````
To enable the IP Address authentication method, you will need to edit
the configuration option ``auth_methods`` to include ``ipaddress``.

Once this is done, when a user first logs in their IP address will be
associated with their account, and subsequent logins will allow them to log
in without authenticating.

If desired, you can edit or pre-fill the IP address associated with an
account from the Users page.

X-Headers
`````````
To enable the X-Headers authentication method, you will need to edit
the configuration option ``auth_methods`` to include ``xheaders``.

To use this method, the following HTTP headers need to be sent to the
``/login`` URL. This can be done using the squid proxy for example, to
prevent teams from needing to know their own log in information but in an
environment where IP address based auth is not feasible (multi site over the
internet contest).

- ``X-DOMjudge-Login`` - Contains the username
- ``X-DOMjudge-Pass``  - Contains the user's password, base64 encoded

Squid configuration for this might look like::

  acl autologin url_regex ^http://localhost/domjudge/login
  request_header_add X-DOMjudge-Login "$USERNAME" autologin
  request_header_add X-DOMjudge-Pass "$BASE64_PASSWORD" autologin

Self-registration
`````````````````
There is also a configuration option to allow teams to self-register with
the system: ``registration_category_name``. When left empty, no self-registration
is allowed; when filled with a category name, newly registered teams will
be placed in this category. During registration, a team can specify their
affiliation.

Executables
-----------
DOMjudge supports executable archives (uploaded and stored in ZIP
format) for configuration of languages, special run and compare
programs. The archive must contain an executable file named
``build`` or ``run``. When deploying a new (or changed)
executable to a judgehost ``build`` is executed *once* if
present. Afterwards an executable file ``run`` must exist (it may
have existed before), that is called to execute the compile, compare,
or run script. The specific formats are detailed below.

Executables may be changed via the web interface in an online editor
or by uploading a replacement zip file. Changes apply immediately to
all further uses of that executable.

Programming languages
---------------------
Compilers can be configured by creating or selecting/editing an executable in
the web interface. When compiling a set of source files, the ``run``
executable is invoked with the following arguments: destination file name,
memory limit (in kB), main (first) source file, other source files.
For more information, see for example the executables ``c`` or
``java_javac_detect`` in the web interface. For many common languages
compile scripts are already included.

Interpreted languages and non-statically linked binaries (for example,
Python or Java) can in also be used, but require that all
runtime dependencies are added to the chroot environment. For details,
see the section :ref:`make-chroot`.

Interpreted languages do not generate an executable and in principle
do not need a compilation step. However, to be able to use interpreted
languages (also Python and Java), during the compilation step a script
must be generated that will function as the executable: the script
must run the interpreter on the source. See for example ``pl``
and ``java_javac_detect`` in the list of executables.

Special run and compare programs
--------------------------------
To allow for problems that do not fit within the standard scheme of
fixed input and/or output, DOMjudge has the possibility to change the
way submissions are run and checked for correctness.

The back end script ``testcase_run.sh`` that handles
the running and checking of submissions, calls separate programs
for running submissions and comparison of the results. These can be
specialised and adapted to the requirements per problem. For this, one
has to create executable archives as described above.
Then the executable must be
selected in the ``special_run`` and/or ``special_compare``
fields of the problem (an empty value means that the default run and
compare scripts should be used; the defaults can be set in the global
configuration settings). When creating custom run and compare
programs, we recommend re-using wrapper scripts that handle the
tedious, standard part. See the boolfind example for details.

Compare programs
----------------
Compare scripts/programs should follow the
`problemarchive.org output validator format
<https://www.problemarchive.org/wiki/index.php/Output_validator>`_.
DOMjudge uses the `default output validator
<https://www.problemarchive.org/wiki/index.php/Problem_Format#Output_Validators>`_
specified there as its default, which can be found at
https://github.com/Kattis/problemtools/blob/master/support/default_validator/.

Note that DOMjudge only supports a subset of the functionality
described there. In particular, the calling syntax is::

  /path/to/compare_script/run <testdata.in> <testdata.ans> <feedbackdir> <compare_args> < <program.out>;

where ``testdata.in`` ``testdata.ans`` are the jury
reference input and output files, ``feedbackdir`` the directory
containing e.g. the judging response file ``judgemessage.txt`` to
be written to (the only other permitted files there
are ``teammessage.txt score.txt judgeerror.txt diffposition.txt``,
``compare_args`` a list of arguments that can set when
configuring a contest problem, and ``program.out`` the team's
output. The validator program should not make any assumptions on its
working directory.

For more details on writing and modifying a compare (or validator)
scripts, see the ``boolfind_cmp`` example and the comments at the
top of the file ``testcase_run.sh``.

Run programs
------------
Special run programs can be used, for example, to create an interactive
problem, where the contestants' program exchanges information with a
jury program and receives data depending on its own output. The
problem ``boolfind`` is included as an example interactive
problem, see ``docs/examples/boolfind.pdf`` for the description.

Usage is similar to compare programs: you can either create a program
``run`` yourself, or use the provided wrapper script, which
handles bi-directional communication between a jury program and the
contestants' program on stdin/stdout (see the ``run``
file in the ``boolfind_run`` executable).

For the first case, the calling syntax that the program must accept is
equal to the calling syntax of ``run_wrapper``, which is
documented in that file. When using ``run_wrapper``, you should
copy it to ``run`` in your executable archive.
The jury must write a program named exactly ``runjury``,
accepting the calling syntax::

  runjury <testdata.in> <program.out>

where the arguments are files to read input testdata from and write
program output to, respectively. This program will communicate via
stdin/stdout with the contestants' program. A special compare program
must probably also be created, so the exact data written to
``program.out`` is not important, as long as the
correctness of the contestants' program can be deduced from the
contents by the compare program.


Printing
--------
It is recommended to configure the local desktop printing of team
workstations where ever possible: this has the most simple interface
and allows teams to print from within their editor.

If this is not feasible, DOMjudge includes support for printing via
the DOMjudge web interface: the DOMjudge server then needs to be
able to deliver the uploaded files to the printer. It can be
enabled via the ``print_command`` configuration option in
the administrator interface. Here you can enter a command that will
be run to print the files. The command you enter can have the
following placeholders:

- ``[file]``: the location on disk of the file to print.
- ``[original]``: the original name of the file.
- ``[language]``: the ID of the language of the file. Useful for syntax highlighting.
- ``[username]``: the username of the user who is printing.
- ``[teamname]``: the teamname of the user who is printing.
- ``[teamid]``: the team ID of the user who is printing.
- ``[location]``: the location of the user's team.

``[language]``, ``[teamname]``, ``[teamid]`` and
``[location]`` can be empty. Placeholders will be shell-escaped before
passing them to the command. The standard output of the command will
be shown in the web interface. If you also want to show standard error,
add ``2>&1`` to the command.

For example, to send the first 10 pages of the file to the default printer
using ``enscript`` and add the username in the page header,
you can use this command::

  enscript -b [username] -a 0-10 -f Courier9 [file] 2>&1

.. _multiple-judgedaemons:

Multiple judgedaemons per machine
---------------------------------
You can run multiple judgedaemons on one multi-CPU or multi-core
machine, dedicating one CPU core to each judgedaemon using Linux
cgroups.

To that end, add extra unprivileged users to the system, i.e. add users
``domjudge-run-X`` (where ``X`` runs through
``0,1,2,3``) with ``useradd`` as described in the section
*installation of a judgehost*.

You can then start each of the judgedaemons with::

  judgedaemon -n X

to bind it to core ``X``.

Although each judgedaemon process will be bound to one single CPU
core, shared use of other resources such as disk I/O might
still have effect on run times.

Multi-site contests
-------------------
This manual assumed you are running a singe-site contest; that is, the teams
are located closely together, probably in a single physical location. In a
multi-site or distributed contest, teams from several remote locations use the
same DOMjudge installation. An example is a national contest where teams can
participate at their local institution.

One option is to run a central installation of
DOMjudge to which the teams connect over the internet. It is here where
all submission processing and judging takes place. Because DOMjudge uses a web
interface for all interactions, teams and judges will interface with the system
just as if it were local.  Still, there are some specific considerations for a
multi-site contest.

Network: there must be a relatively reliable network connection between the
locations and the central DOMjudge installation, because teams cannot submit or
query the scoreboard if the network is down. Because of traversing an unsecured
network, you should consider HTTPS for encrypting the traffic.  If you
want to limit teams' internet access, it must be done in such a way that the remote
DOMjudge installation can still be reached.

Team authentication: the IP-based authentication will still work as long as
each team workstation has a different public IP address. If some teams are
behind a NAT-router and thus all present themselves to DOMjudge with the same
IP-address, another authentication scheme must be used (e.g. PHP sessions).

Judges: if the people reviewing the submissions will be located remotely as
well, it's important to agree beforehand on who-does-what, using the
submissions claim feature and how responding to incoming clarification requests
is handled. Having a shared chat/IM channel may help when unexpected issues
arise.

Scoreboard: by default DOMjudge presents all teams in the same scoreboard.
Per-site scoreboards can be implemented either by using team categories or
team affiliations in combination with the scoreboard filtering option.


As an alternative, each site can run their own DOMjudge installation, and
each site will have a local scoreboard with their own teams. It is possible
to create a merged scoreboard out of these individual installations with the
console command ``scoreboard:merge``. You need to know for each site which
contest ID to use, and the IDs of the team categories you want to include
(comma separated). You can then run it like this::

  webapp/bin/console scoreboard:merge 'Combined Scoreboard Example' \
     https://judge.example1.edu/api/v4/contests/3/ 3 \
     https://chipcie.example2.org/api/v4/contests/2/ 2,3  \
     https://domjudge.aapp.example.nl/api/v4/contests/6/ 3
