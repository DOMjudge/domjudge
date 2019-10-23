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

Command line submit client
--------------------------
DOMjudge comes with a command line submit client which makes it really
convenient for teams to submit their solutions to DOMjudge.

In order to build the submit client, you need libcURL, libJSONcpp and
optionally libmagic. To install this on Debian-like distributions::

  sudo apt install libcurl4-gnutls-dev libjsoncpp-dev libmagic-dev

Or on RedHat/CentOS/Fedora::

  sudo yum install libcurl-devel jsoncpp-devel file-devel

Then run (adapt the URL to your environment)::

  ./configure --enable-static-linking --with-baseurl="https://yourhost.example.edu/domjudge"
  make submitclient

You can now copy this client from ``submit/submit`` to the workstations.

In order for the client to authenticate to DOMjudge, credentials can be
pre-provisioned in the file ``~/.netrc`` in the user's homedir, with example
content::

  machine yourhost.example.edu login user0123 password Fba^2bHzz

See the netrc(4) manual page for more details. You can run ``./submit --help``
to inspect its configuration and options.

Rebuilding team documentation
-----------------------------

The team manual is only available in PDF format and must be built from
the LaTeX sources in `doc/team` *after* configuration of the
system.

.. note::

  A prebuilt team manual is included, but this contains
  default/example values for site-specific configuration settings such
  as the team web interface URL and judging settings such as the memory
  limit. We strongly recommend rebuilding the team manual to include
  site-specific settings and also to revise it to reflect your contest
  specific environment and rules.


The team manual requires a working LaTeX installation and some packages
available in the `texlive-latex-extra` package in any modern Linux
distribution.

When DOMjudge is configure and site-specific
configuration set, the team manual can be generated with the command
`genteammanual` found under `docs/team`. The PDF
document will be placed in the current
directory or a directory given as argument.
The following should do it on a Debian-like system::

  sudo apt install make texlive-latex-extra texlive-latex-recommended texlive-lang-european
  cd <INSTALL_PATH>/docs/team
  ./genteammanual [targetdir]

