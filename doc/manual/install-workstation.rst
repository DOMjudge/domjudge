Installation of the team workstations
=====================================

To access DOMjudge, a team workstation needs nothing more than a modern
webbrowser, like a recent version of Chrome, Firefox or Edge. We do not
support legacy browsers like Internet Explorer. Of course the machine
also needs the appropriate development tools for the languages you want
to support.

The web browser needs to access the domserver via HTTP(S). It may be
convenient to teams if the URL of DOMjudge is set as the default homepage,
and if using a self-signed HTTPS certificate, that the browser is made
to trust this certificate.

.. _submit_client_requirements:

Command line submit client
--------------------------
DOMjudge comes with a command line submit client which makes it really
convenient for teams to submit their solutions to DOMjudge.

In order to use the submit client, you need Python, the python requests 
library and optionally the python magic library installed on the team's
workstation. To install this on Debian-like distributions::

  sudo apt install python3 python3-requests python3-magic

Or on RedHat/CentOS/Fedora::

  sudo yum install python3 python3-requests python3-magic

You can now copy this client from ``submit/submit`` to the workstations.

The submit client needs to know the base URL of the domserver where it should
submit to. You have three options to configure this:

* Set it as an environment variable called ``SUBMITBASEURL``, e.g. in
  ``/etc/profile.d/``.
* Modify the ``submit/submit`` file and set the variable of ``baseurl``
  at the top.
* Let teams pass it using the ``--url`` argument.

Note that the environment variable overrides the hardcoded variable at
the top of the file and the ``--url`` argument overrides both other options.

The submit client will need to know to which contest to submit to. If there
is only one active contest, that will be used. If not, you have two options
to configure this:

* Set it as an environment variable called ``SUBMITCONTEST``, e.g. in
  ``/etc/profile.d/``.
* Let teams pass it using the ``--contest`` argument.

Note that the ``--contest`` argument overrides the environment variable.

In order for the client to authenticate to DOMjudge, credentials can be
pre-provisioned in the file ``~/.netrc`` in the user's homedir, with example
content::

  machine yourhost.example.edu login user0123 password Fba^2bHzz

See the `netrc manual page`_ for more details. You can run ``./submit --help``
to inspect its configuration and options.

Rebuilding team documentation
-----------------------------

The source of the team manual can be found in ``doc/manual/team.rst``.
The team manual can incorporate specific settings of your environment,
most notably the URL of the DOMjudge installation. To achieve this,
rebuild the team manual *after* configuration of the system.

.. note::

  A prebuilt team manual is included, but this contains
  default/example values for site-specific configuration settings such
  as the team web interface URL and judging settings such as the memory
  limit. We strongly recommend rebuilding the team manual to include
  site-specific settings and also to revise it to reflect your contest
  specific environment and rules.


When DOMjudge is configured and site-specific configuration set,
the team manual can be generated with the command ``make docs``.
The following should do it on a Debian-like system::

  sudo apt install python-sphinx python-sphinx-rtd-theme rst2pdf fontconfig python3-yaml
  cd <INSTALL_PATH>/doc/
  make docs

On Debian 11 and above, install
``python3-sphinx python3-sphinx-rtd-theme rst2pdf fontconfig python3-yaml`` instead.

The resulting manual will then be found in the ``team/`` subdirectory.

.. _netrc manual page: https://ec.haxx.se/usingcurl/usingcurl-netrc
