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
  been tested with Debian and Ubuntu on AMD64, but should work on other environments.
  See our `wiki <https://github.com/DOMjudge/domjudge/wiki/Running-DOMjudge-in-WSL>`_ for information about DOMjudge and WSLv2.
* It is necessary that you have root access.
* A TCP/IP network which connects the DOMserver and the judgehosts.
  The machines only need HTTP(S) access to the DOMserver.


Software requirements
`````````````````````

* Sudo
* Debootstrap
* PHP command line interface with the ``curl``, ``json``, ``xml``,
  ``zip`` extensions.

For Debian::

  sudo apt install make pkg-config sudo debootstrap libcgroup-dev \
        php-cli php-curl php-json php-xml php-zip lsof procps

For Red Hat::

  sudo yum install make pkgconfig sudo libcgroup-devel lsof \
        php-cli php-mbstring php-xml php-process procps-ng

Removing apport
---------------

Some systems (like Ubuntu) ship with ``apport``, which conflicts with judging.
To uninstall it, run::

  sudo apt remove apport

.. _installing-judgehost:

Building and installing
-----------------------
These instructions assume a release `tarball <https://www.domjudge.org/download>`_, see :ref:`this section <bootstrap>`
for instructions to build from git sources.

After installing the software listed above, run configure. In this
example to install DOMjudge in the directory ``domjudge`` under your
home directory::

  ./configure --prefix=$HOME/domjudge
  make judgehost
  sudo make install-judgehost

The judgedaemon can be run on various hardware configurations;

- A virtual machine, typically these have 1 or 2 cores and no hyperthreading, because the kernel will schedule its own tasks on CPU 0, we advice CPU 1,
- A default office machine, these sometimes have hyperthreading. Verify if the machine has hyperthreading and consider turning it off and as a rule of thumb pick CPU 2 as CPU 1 could be a hyperthreading core, be on the same die as CPU 0 and therefore share memory with that CPU. If more cores available as a rule of thumb moving to the highest CPU should be considered.
- Multiple on a single high-grade server with multiple CPUs or a CPU with multiple cores. Check for hyperthreading and if possible run the judgedaemons on separate CPU packages/dies both from each other and when possible different from CPU 0. See the section :ref:`multiple-judgedaemons` for running multiple judgedaemons on a single host.

For the next section we assume a machine with possibly hyperthreading and 3 or more CPUs. This can be checked with::

  lscpu | grep "Thread(s) per core"

having a value above 1 indicates hyperthreading or::

  cat /sys/devices/system/cpu/smt/active

a value of `1` or `on`. The target CPU core to restrict the judgedaemon to below should be in the range of::

  cat /sys/devices/system/cpu/online

For running solution programs under a non-privileged user, a user and group have
to be added to the system that acts as judgehost. This user does not
need a home-directory or password, so the following command would
suffice to add a user and group ``domjudge-run-2`` with minimal privileges
with the judgedaemon restricted to CPU core 2::

  sudo groupadd domjudge-run
  sudo useradd -d /nonexistent -g domjudge-run -M -s /bin/false domjudge-run-2

The ``-2`` suffix corresponds to a judgedaemon bound to CPU core 2
with the option ``-n 2``, see :ref:`start-judgedaemon`. If you do not
want to bind the judgedaemon to a core, create a user ``domjudge-run``
and start the judgedaemon without ``-n`` option.
See the section :ref:`multiple-judgedaemons` for running multiple
judgedaemons on a single host.

Sudo permissions
----------------

The judgedaemon uses a wrapper to isolate programs when compiling
or running the submissions called ``runguard``. This wrapper needs
to be able to become root for certain operations like changing to the
runuser and performing a chroot. Also, the default
``chroot-startstop.sh`` script uses sudo to gain privileges for
certain operations. There's a pregenerated snippet
in ``etc/sudoers-domjudge`` that contains all required rules. You can
put this snippet in ``/etc/sudoers.d/``.

If you change the user you start the judgedaemon as, or the installation
paths, be sure to update the sudoers rules accordingly.

.. _make-chroot:

Creating a chroot environment
-----------------------------

The judgedaemon compiles and executes submissions inside a chroot
environment for security reasons. By default it mounts parts of a
prebuilt chroot tree read-only during this judging process (using
the script ``lib/judge/chroot-startstop.sh``). The chroot needs
to contain the compilers, interpreters and support libraries that
are needed at compile- and at runtime for the supported languages.

This chroot tree can be built using the script
``bin/dj_make_chroot``. On Debian and Ubuntu the same
distribution and version as the host system are used, on other Linux
distributions the latest stable Debian release will be used to build
the chroot. Any extra packages to support languages (compilers and
runtime environments) can be passed with the option ``-i`` or be
added to the ``INSTALLDEBS`` variable in the script. The script
``bin/dj_run_chroot`` runs an interactive shell or a command inside
the chroot. This can be used for example to install new or upgrade
existing packages inside the chroot.
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
Optionally the timings can be made more stable by not letting the OS schedule
any other tasks on the same CPU core the judgedaemon is using:
``GRUB_CMDLINE_LINUX_DEFAULT="quiet cgroup_enable=memory swapaccount=1 isolcpus=2"``

On modern distros (e.g. Debian bullseye and Ubuntu Jammy Jellyfish) which have
cgroup v2 enabled by default, you need to add ``systemd.unified_cgroup_hierarchy=0``
as well. Then run ``update-grub`` and reboot.
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

The script `jvm_footprint` can be used to measure the memory overhead of the JVM for languages such as Kotlin and Java.


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

The exact URL to use can be found in the Config Checker in the
admin web interface; the password here must be identical to that of the
``judgehost`` user. Multiple lines may be specified to allow a
judgedaemon to work for multiple domservers. The id in the first column
is used to differentiate between multiple domservers, and should be
unique within the ``restapi.secret`` file.

.. _start-judgedaemon:

Starting the judgedaemon
------------------------

Finally start the judgedaemon::

  bin/judgedaemon -n 2

Upon its first connection to the domserver API, the judgehost will be
auto-registered and will be by default enabled. If you wish to
add a new judgehost but have it initially disabled, you can change the config
setting to automatically pause judges on first connection or manually add it
through the DOMjudge web interface and set it to disabled before starting
the judgedaemon.

The judgedaemon can also be run as a service by running::

  sudo systemctl enable --now domjudge-judgehost
