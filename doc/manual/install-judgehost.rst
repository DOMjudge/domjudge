Installation of the judgehosts
==============================

A DOMjudge installation requires one or more judgehosts which will perform
the actual compilation and evaluation of submissions.

.. _judgehost_requirements:

Requirements
------------

System requirements
```````````````````

* The operating system is a Linux variant. DOMjudge has mostly
  been tested with Debian and Ubuntu, but should work on other environments.
* It is necessary that you have root access.
* A TCP/IP network which connects the DOMserver and the judgehosts.
  The machines only need HTTP(S) access to the DOMserver.


Software requirements
`````````````````````

* PHP command line interface with the ``curl``, ``json``, ``xml``,
  ``zip`` extensions.
* Compilers for the languages you want to support.

For Debian (with some example compilers)::

  sudo apt install make sudo debootstrap libcgroup-dev lsof \
        php-cli php-curl php-json php-xml php-zip procps \
        gcc g++ default-jre-headless default-jdk-headless \
        ghc fp-compiler

For RedHat::

  sudo yum install make sudo libcgroup-devel lsof \
        php-cli php-mbstring php-xml php-process procps-ng \
        gcc gcc-c++ glibc-static libstdc++-static \
        java-11-openjdk-headless java-11-openjdk-devel \
        ghc-compiler fpc

Building and installing
-----------------------
After installing the software listed above, run configure. In this
example to install DOMjudge in the directory ``domjudge`` under your
home directory::

  ./configure --prefix=$HOME/domjudge
  make judgehost
  sudo make install-judgehost

For running solution programs under a non-privileged user, a user and group have
to be added to the system that acts as judgehost. This user does not
need a home-directory or password, so the following command would
suffice to add a user and group ``domjudge-run`` with minimal privileges::

  sudo useradd -d /nonexistent -U -M -s /bin/false domjudge-run

Sudo permissions
----------------

``Runguard`` needs to be able to become root for certain operations
like changing to the runuser and performing a chroot. Also, the default
``chroot-startstop.sh`` script uses sudo to gain privileges for
certain operations. There's a pregenerated snippet
in ``etc/sudoers-domjudge`` that contains all required rules. You can
put this snippet in ``/etc/sudoers.d/``.

If you change the user you start the judgedaemon as, or the installation
paths, be sure to update the sudoers rules accordingly.

.. _make-chroot:

Creating a chroot environment
-----------------------------

The judgedaemon executes submissions inside a chroot environment for
security reasons. By default it mounts parts of a prebuilt chroot tree
read-only during this judging process (using the script
``lib/judge/chroot-startstop.sh``). This is needed to support
extra languages that require access to interpreters or support
libraries at runtime, for example Java, C#, and any interpreted
languages like Python, Perl, Shell script, etc.

This chroot tree can be built using the script
``bin/dj_make_chroot``. On Debian and Ubuntu the same
distribution and version as the host system are used, on other Linux
distributions the latest stable Debian release will be used to build
the chroot. Any extra packages to support languages can be passed with
the option ``-i`` or be added to the ``INSTALLDEBS``
variable in the script. The script ``bin/dj_run_chroot`` runs an
interactive shell or a command inside the chroot. This can be used for
example to install new or upgrade existing packages inside the chroot.
Run these scripts with option ``-h`` for more information.

Finally, if necessary edit the script ``lib/judge/chroot-startstop.sh``
and adapt it to work with your local system. In case you changed the
default pre-built chroot directory, make sure to also update the sudo
rules and the ``CHROOTORIGINAL`` variable in ``chroot-startstop.sh``.

Linux Control Groups
--------------------

DOMjudge uses Linux Control Groups or *cgroups* for process isolation in
the judgedaemon. Linux cgroups give more accurate measurement of
actually allocated memory than traditional resource limits (which is
helpful with interpreters like Java that reserve but do not actually use
lots of memory). Also, cgroups are used to restrict network access so
no separate measures are necessary, and they allow running
:ref:`multiple judgedaemons <multiple-judgedaemons>`
on a multi-core machine by using CPU binding.

The judgedaemon needs to run a recent Linux kernel (at least 3.2.0). The
following steps configure cgroups on Debian. Instructions for other
distributions may be different (send us your feedback!).

Edit grub config to add cgroup memory and swap accounting to the boot
options. Edit ``/etc/default/grub`` and change the default
commandline to
``GRUB_CMDLINE_LINUX_DEFAULT="quiet cgroup_enable=memory swapaccount=1"``
Then run ``update-grub`` and reboot.
After rebooting check that ``/proc/cmdline`` actually contains the
added kernel options. On VM hosting providers such as Google Cloud or
DigitalOcean, ``GRUB_CMDLINE_LINUX_DEFAULT`` may be overwritten
by other files in ``/etc/default/grub.d/``.

You have now configured the system to use cgroups. To create
the actual cgroups that DOMjudge will use, run::

  sudo systemctl enable create-cgroups --now

Note that this service will automatically be started if you use the
``domjudge-judgehost`` service, see below. Alternatively, you can
customize the script ``judge/create_cgroups`` as required and run it
after each boot.


REST API credentials
--------------------

The judgehost connects to the domserver via a REST API. You need to
create an account in the DOMjudge web interface for the judgedaemons
to use (this may be a shared account between all judgedaemons) with
a difficult, random password and the 'judgehost' role.

On each judgehost, copy from the domserver (or create) a file
``etc/restapi.secret`` containing the id, URL,
username and password whitespace-separated on one line, for example::

  default http://example.edu/domjudge/api/  judgehost  MzfJYWF5agSlUfmiGEy5mgkfqU

The password here must be identical to that of the ``judgehost`` user
in the admin web interface. Multiple lines may be specified to allow a
judgedaemon to work for multiple domservers. The id in the first column
is used to differentiate between multiple domservers, and should be
unique within the ``restapi.secret`` file.

Starting the judgedaemon
------------------------

Finally start the judgedaemon::

  bin/judgedaemon

Upon its first connection to the domserver API, the judgehost will be
auto-registered and will be by default enabled. If you wish to
add a new judgehost but have it initially disabled, you can add it
manually through the DOMjudge web interface and set it to disabled
before starting the judgedaemon.

The judgedaemon can also be run as a service by running::

  sudo systemctl enable domjudge-judgehost
  sudo systemctl start  domjudge-judgehost
