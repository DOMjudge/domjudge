Development
===========

.. _API:

API
```
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
`````````````````````````````````````````
The installation steps in this document assume that you are using a
downloaded tarball from the DOMjudge website. If you want to install
from Git repository sources, because you want to use the bleeding edge
code or consider to send a patch to the developers, the
configure/build system first has to be bootstrapped.

This requires the GNU autoconf/automake toolset to be installed,
and various tools to build the documentation.

On Debian(-based) systems, the following apt command should
install the additionally required packages::

  sudo apt install autoconf automake \
    python3-sphinx python3-sphinx-rtd-theme \
    texlive-latex-recommended texlive-latex-extra \
    texlive-fonts-recommended texlive-lang-european

When this software is present, bootstrapping can be done by running
``make dist``, which creates the ``configure`` script,
downloads and installs the PHP dependencies via composer and
generates documentation from RST/LaTeX sources.

Maintainer mode installation
````````````````````````````
DOMjudge provides a special maintainer mode installation.
This method does an in-place installation within the source
tree. This allows one to immediately see effects when modifying
code.

This method requires some special steps which can most easily
be run via makefile rules as follows::

  sudo apt install acl
  make maintainer-conf [CONFIGURE_FLAGS=<extra options for ./configure>]
  make maintainer-install

Note that these targets have to be executed *separately* and
they replace the steps described in the chapters on installing
the DOMserver or Judghost.

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
``````````````````
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
