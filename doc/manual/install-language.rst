Appendix: Installing the example languages
==========================================

DOMjudge ships with some default languages with a default configuration.
As you might set up contests with those languages we provide how those languages were
installed in the past as guideline. Use ``dj_run_chroot`` for most of those packages, and see
the section :ref:`make-chroot` for more information.

Most of the languages can be installed from the table below as there is a package available
to install inside the judging chroot. Given that you can install your own chroot we only provide the
packages for Ubuntu as that is the most used at the moment of writing.

.. list-table:: Packages for languages
   :header-rows: 1

   * - Language
     - Ubuntu package
     - Remarks
   * - Ada
     - `gnat`
     -
   * - AWK
     - `mawk`/`gawk`
     - `mawk` is default installed
   * - Bash
     - `bash`
     - Default installed in the chroot
   * - C
     - `gcc`
     - Default installed in the chroot
   * - C++
     - `g++`
     - Default installed in the chroot
   * - C#
     - `mono-mcs`
     -
   * - Fortran
     - `gfortran`
     -
   * - Haskell
     - `ghc`
     - After installing you need to move these files
       `/{usr->var}/lib/ghc/package.conf.d` as `/var`
       is not mounted during compilation.
   * - Java
     - `default-jdk-headless`
     - Default installed in the chroot
   * - Javascript
     - `nodejs`
     -
   * - Kotlin
     - `kotlin`
     -
   * - Lua
     - `lua5.4`
     - Ubuntu does not ship a generic meta package (yet).
   * - Pascal
     - `fp-compiler`
     -
   * - Perl
     - `perl-base`
     - Default installed in the chroot
   * - POSIX shell
     - `dash`
     - Default installed in the chroot
   * - Prolog
     - `swi-prolog-core-packages`
     -
   * - Python3
     - `pypy3`/`python3`
     - Default installed in the chroot.
       DOMjudge assumes `pypy3` as it runs faster in general.
       Consider the `PyPy3 PPA`_ if you need the latest python3 features. PyPy3 does not have 100%
       compatibility with all non-standard libraries. In case this is needed you should reconsider the default
       CPython implementation.
   * - OCaml
     - `ocaml`
     -
   * - R
     - `r-base-core`
     -
   * - Ruby
     - `ruby`
     -
   * - Rust
     - `rustc`
     -
   * - Scala
     - `scala`
     -
   * - Swift
     -
     - See the `Swift instructions`_, unpack the directory in the chroot and install `libncurses6`. Depending
       on where you install the directory you might need to extend the `PATH` in the `run` script.

.. _PyPy3 PPA: https://launchpad.net/~pypy/+archive/ubuntu/ppa
.. _Swift instructions: https://www.swift.org/documentation/server/guides/deploying/ubuntu.html
