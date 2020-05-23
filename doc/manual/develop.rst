Development
===========

.. _API:

API
---
DOMjudge comes with a fully featured REST API. It is based on the
`CCS Contest API specification
<https://clics.ecs.baylor.edu/index.php?title=Contest_API>`_
to which some DOMjudge-specific API endpoints have been added. Full documentation
on the available API endpoints can be found at
`http(s)://yourhost.example.edu/domjudge/api/doc`.

DOMjudge also offers an
`OpenAPI Specification ver. 2
<https://swagger.io/docs/specification/2-0/basic-structure/>`_
compatible JSON file, which can be found at
`http(s)://yourhost.example.edu/domjudge/api/doc.json`.


Bootstrapping from Git repository sources
-----------------------------------------
The installation steps in this document assume that you are using a
downloaded tarball from the DOMjudge website. If you want to install
from Git repository sources, because you want to use the bleeding edge
code or consider to send a patch to the developers, the
configure/build system first has to be bootstrapped.

This requires the GNU autoconf/automake toolset to be installed,
and various tools to build the documentation.

On Debian(-based) systems, the following apt command should
install the packages that are required (additionally to the ones
already listed under
:ref:`domserver <domserver_requirements>`,
:ref:`judgehost <judgehost_requirements>` and
:ref:`submit client <submit_client_requirements>` requirements)::

  sudo apt install autoconf automake bats \
    python-sphinx python-sphinx-rtd-theme rst2pdf fontconfig python3-yaml

On Debian 11 and above, install
``python3-sphinx python3-sphinx-rtd-theme rst2pdf fontconfig python3-yaml`` instead.

When this software is present, bootstrapping can be done by running
``make dist``, which creates the ``configure`` script,
downloads and installs the PHP dependencies via composer and
generates documentation from RST/LaTeX sources.

Maintainer mode installation
----------------------------
DOMjudge provides a special maintainer mode installation.
This method does an in-place installation within the source
tree. This allows one to immediately see effects when modifying
code.

This method requires some special steps which can most easily
be run via makefile rules as follows::

  make maintainer-conf [CONFIGURE_FLAGS=<extra options for ./configure>]
  make maintainer-install

Note that these targets have to be executed *separately* and
they replace the steps described in the chapters on installing
the DOMserver or Judgehost.

While working on DOMjudge, it is useful to run the Symfony webapp in
development mode to have access to the profiling and debugging
interfaces and extended logging. To run in development mode, create
the file ``webapp/.env.local`` and add to it the setting
``APP_ENV=dev``. This is automatically done when running ``make
maintainer-install`` when the file did not exist before.
For more details see
`https://symfony.com/doc/current/configuration/dot-env-changes.html`.

The file ``webapp/.env.test`` (and ``webapp/.env.test.local`` if it
exists) are loaded when you run the unit tests. You can thus place any
test-specific settings in there.

Makefile structure
------------------
The Makefiles in the source tree use a recursion mechanism to run make
targets within the relevant subdirectories. The recursion is handled
by the ``REC_TARGETS`` and ``SUBDIRS`` variables and the
recursion step is executed in ``Makefile.global``. Any target
added to the ``REC_TARGETS`` list will be recursively called in
all directories in ``SUBDIRS``. Moreover, a local variant of the
target with ``-l`` appended is called after recursing into the
subdirectories, so recursion is depth-first.

The targets ``dist``, ``clean``, ``distclean``, ``maintainer-clean``
are recursive by default, which means that these call their local
``-l`` variants in all directories containing a Makefile. This
allows for true depth-first traversal, which is necessary to correctly
run the ``*clean`` targets: otherwise e.g. ``paths.mk`` will
be deleted before subdirectory ``*clean`` targets are called that
depend on information in it.

Running the test suite
----------------------

The DOMjudge sources ship with a comprehensive test-suite that contains
unit, integration and functional tests to make sure the system works.

These tests live in the ``webapp/tests`` directory.

To run them, follow the following steps:

* Make sure you have a working DOMjudge installation.
* Make sure your database contains only the sample data. This can be done by
  first dropping any existing database and then running
  ``bin/dj_setup_database -u root -r install``.

Note that you don't have to drop and recreate the database everytime you run the
tests; the tests are written in such a way that they keep working, even if you
run them multple times.

Now to run the tests, execute the command::

  lib/vendor/bin/phpunit -c webapp/phpunit.xml.dist

This command can take an argument ``--filter`` to which you can pass a string
which will be used to filter which tests to run. For example, to run only the
jury print controller tests, run::

  lib/vendor/bin/phpunit -c webapp/phpunit.xml.dist --filter \
    'App\\Tests\\Controller\\Jury\\PrintControllerTest'

Or to run only one test in that class, you can run::

  lib/vendor/bin/phpunit -c webapp/phpunit.xml.dist --filter \
    'App\\Tests\\Controller\\Jury\\PrintControllerTest::testPrintingDisabledJuryIndexPage

Note that most IDEs have support for running tests inside of them, so you don't
have to type these filters manually. If you use such an IDE, just make sure to
specify the `webapp/phpunit.xml.dist` file as a PHPUnit configuration file and
it should work.
