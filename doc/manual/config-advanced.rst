Advanced configuration topics
=============================

Adding graphics, custom styling and custom JavaScript
-----------------------------------------------------
DOMjudge can optionally present country flags, affiliation logos,
team pictures and a page-wide banner on the public interface.

You can place the images under the path `public/images/` (see
the Config checker in the admin interfae for the full filesystem
path of your installation) as follows:

- *Country flags* are shown when the ``show_flags`` configuration option
  is enabled. The `flag-icon-css <https://github.com/lipis/flag-icon-css>`_
  is used for the flag images.
- *Affiliation logos*: these will be shown with the teams that are
  part of the affiliation, if the ``show_affiliation_logos`` configuration
  option is enabled. They can be placed in
  `public/images/affiliations/1234.png` where *1234* is the numeric ID
  of the affiliation as shown in the DOMjudge interface. There is a
  separate option ``show_affiliations`` that independently controls where
  the affiliation *names* are shown on the scoreboard. These logos should be
  square and be at least 64x64 pixels, but not much bigger.
- *Team pictures*: a photo of the team will be shown in the team details
  page if `public/images/teams/456.jpg` exists, where *456* is the
  team's numeric ID as shown in the DOMjudge interface. DOMjudge will not
  modify the photos in any way or form, so make sure you don't upload photos
  that are too big, since that will incur a lot of network traffic.
- *Contest Banners*: a page-wide banner can be shown on the public scoreboard
  if that image is placed in `public/images/banners/1.png` where *1* is the
  contest's numeric ID as shown in the DOMjudge interface. Alternatively, you
  can place a file at `public/images/banner.png` which will be used as a banner
  for all contests. Contest-specific banners always have priority. Contest
  banners usually are rectangular, having a width of around 1920 pixels and a
  height of around 300 pixels. Other ratio's and sizes are supported, but check
  the public scoreboard to see how it looks.

.. note::

  The IDs for affiliations, teams and contests need to be the *external ID*
  if the ``data_source`` setting of DOMjudge is set to external.

It is also possible to load custom CSS and/or JavaScript files. To do so, place
files ending in `.css` under `public/css/custom/` and/or files ending in `.js`
under `public/js/custom/`. See the Config checker in the admin interface for the
full filesystem path of your installation. Note that there is no guaranteed
order in which the files will be loaded, but they will all be loaded after the
main DOMjudge CSS and JavaScript files. If you have a lot of custom CSS/JavaScript
files in these directories, the response time of DOMjudge might decrease, so it
is recommended to only place a few files there.

.. note::

  If you add or remove any of the above files, you need to
  :ref:`clear the cache <clear-cache>` for changes to be detected.

Adding links to documentation to the team interface
---------------------------------------------------

DOMjudge supports adding links to documentation to the team interface.
First, on the DOMserver, copy the file ``etc/docs.yaml.dist`` to
``etc/docs.yaml`` and modify its contents. The ``.dist`` file contains
comments as to what each field in the file means and how to use it. If you
want to link to files served by the webserver of the DOMserver, place them
under `/public/docs/`. See the Config checker in the admin interface for
the full filesystem path of your installation. All links open in a new
tab / window.

.. _authentication:

Authentication and registration
-------------------------------
Out of the box users are able to authenticate using username and password.

Two other native authentication methods are available:

- IP Address - authenticates users based on the IP address they are accessing
  the system from;
- X-Headers - authenticates users based on some HTTP header values.

Besides this, DOMjudge can be configured with any provider that can set
the environment variable ``REMOTE_USER`` to an existing username,
for example LDAP, SAML, CAS or OpenID connect modules for Apache.

There's an option to let teams register themselves in the system.

IP Address
``````````
To enable the IP Address authentication method, you will need to edit
the configuration option ``auth_methods`` to include ``ipaddress``.

Once this is done, when a user first logs in their IP address will be
associated with their account, and subsequent logins will allow them to log
in without authenticating.

If desired, you can edit or pre-fill the IP address associated with an
account from the Users page. When using IPv6, ensure that you enter the
address in the exact representation as the webserver reports it (e.g.
as visible in the webserver logs) - no canonicalization is performed.

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

Using REMOTE-USER
`````````````````
DOMjudge supports generic authentication by various existing providers that
can authenticate a user and set the ``REMOTE_USER`` environment variable
to the authenticated username.

Examples of this are several Apache modules: mod LDAP, Shibboleth or
Mod Mellon for SAML 2.0, mod Auth CAS, mod OpenID Connect, or mod Kerb for
Kerberos.

This does not currently allow for auto-provisioning or self-registration,
the users must already exist in DOMjudge and their DOMjudge username must
match what is in the ``REMOTE_USER`` variable.

Set up the respective module to authenticate incoming users for the URL
path of your installation. Then, in ``webapp/config/packages/security.yaml``
change the ``main`` section of your source tree to add a ``remote_user``
key after ``form_login`` that looks like this::

         main:
             pattern: ^/
             …
             form_login:
                 login_path: login
                 check_path: login
                 csrf_token_generator: security.csrf.token_manager
                 use_referer: true
             remote_user:
                 provider: domjudge_db_provider

And re-run the "make install" command to deploy this change.
Or alternatively remove the entire ``var/cache/prod/`` directory when
editing ``security.yaml`` on an already deployed location.

If the thus authenticated user is not found in DOMjudge, the application
will present the standard username/password login screen as a fallback.

Changing the User password hashing cost
```````````````````````````````````````
The hashing cost can be changed in ``webapp/config/packages/security.yaml``, change the encoder section:

    encoders:
        App\Entity\User:
            algorithm: 'bcrypt'
            cost: 7

For bcrypt (current encoder) each increase in cost will double the time per password.

See the `Symfony docs`_ for more info on this subject.

.. _Symfony docs: https://symfony.com/doc/current/reference/configuration/security.html

Self-registration
`````````````````
Teams can be allowed to self-register with the system. To enable it, go to
the team category you want the self-registered teams to become part of and
enable self-registration for that category. The option will be shown on the
login screen if it has been enabled for at least one category. When multiple
categories are set to allow, teams can choose one of them during registration.
You can assign the respective categories to the contest(s) these teams may
participarte in.

During registration, teams can also specify their affiliation,
if the global configuration option 'show affiliations' is enabled.

Executables
-----------
DOMjudge supports executable archives (uploaded and stored in ZIP
format) for configuration of languages, special run and compare
programs. The archive must contain an executable file named
``build`` or ``run``. When deploying a new (or changed)
executable to a judgehost ``build`` is executed *once* if
present (inside the chroot environment that is also used for
compiling and running submissions). Afterwards an executable
file ``run`` must exist (it may have existed before), that is
called to execute the compile, compare, or run script. The
specific formats are detailed below.

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
Compare scripts/programs should follow the `Output Validators format`_
DOMjudge uses the `default output validator`_ from the problem package
format as its default.

Note that DOMjudge only supports a subset of the functionality
described there. In particular, the calling syntax is::

  /path/to/compare_script/run <testdata.in> <testdata.ans> <feedbackdir> <compare_args> < <program.out>;

where ``testdata.in`` ``testdata.ans`` are the jury
reference input and output files, ``feedbackdir`` the directory
containing the judging response files ``judgemessage.txt``
and ``judgeerror.txt``,
``compare_args`` a list of arguments that can set when
configuring a contest problem, and ``program.out`` the team's
output. The validator program should not make any assumptions on its
working directory.

For more details on writing and modifying a compare (or validator)
script, see the ``boolfind_cmp`` example and the comments at the
top of the file ``testcase_run.sh``.

Run programs
------------
Special run programs can be used, for example, to create an interactive
problem, where the contestants' program exchanges information with a
jury program and receives data depending on its own output. The
problem ``boolfind`` is included as an example interactive
problem, see ``doc/examples/boolfind.pdf`` for the description.

The calling syntax is::

  /path/to/run_script/run <testdata.in> <testdata.ans> <feedbackdir> <run args> < <program.out>;

Usage is similar to compare programs. DOMjudge wraps the run program to handle
bi-directional communication between the run program and the contestants'
program. Anything you write to stdout is forwarded to the contestants' program,
anything the contestants' program writes is forwarded to your stdin.

See the ``validate.h`` file in the ``boolfind_run`` executable for some
convenience functions you might want to use when implementing your own run
program.

.. _printing:

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
``domjudge-run-X`` (where ``X`` runs through ``1,2,3,...``) with
``useradd`` as described in the section :ref:`installing-judgehost`.

You can then start each of the judgedaemons with::

  judgedaemon -n X

to bind it to core ``X`` and user ``domjudge-run-X``. If you use
systemd, then edit the ``domjudge-judgehost.target`` unit file and add
more judgedaemons there.

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

.. _Output Validators format: https://icpc.io/problem-package-format/spec/output_validators
.. _default output validator: https://icpc.io/problem-package-format/spec/problem_package_format#default-output-validator-specification

.. _clear-cache:

Clearing the PHP/Symfony cache
------------------------------

Some operations require you to clear the PHP/Symfony cache. To do this, execute
the `webapp/bin/console` (see the Config checker in the admin interfae for the
full filesystem path of your installation) binary with the `cache:clear` subcommand::

  webapp/bin/console cache:clear

Note that this is different than clearing the scoreboard cache.

Sending errors to Sentry
------------------------

DOMjudge has the possibility to send any errors to `Sentry`_. First, create an
organization and project in Sentry and copy the Sentry DSN. Then create the file
``webapp/.env.local`` and add to it the setting ``SENTRY_DSN=<dsn>`` where
``<dsn>`` is the Sentry DSN you copied. Then :ref:`clear the cache <clear-cache>`
for this change to take effect. Now all errors should appear in Sentry
automatically.

.. _Sentry: http://sentry.io
