DOMjudge
--------
[![Build Status](https://travis-ci.org/DOMjudge/domjudge.svg?branch=master)](https://travis-ci.org/DOMjudge/domjudge)
[![Coverity Scan Status](https://img.shields.io/coverity/scan/671.svg)](https://scan.coverity.com/projects/domjudge)
[![Code Climate](https://codeclimate.com/github/DOMjudge/domjudge/badges/gpa.svg)](https://codeclimate.com/github/DOMjudge/domjudge)

This is the Programming Contest Jury System "DOMjudge" version 6.0.1

DOMjudge is a system for running a programming contest, like the
ACM ICPC regional and world championship programming contests.


Documentation
-------------

For more information on installation and requirements see the
documentation under the doc/admin directory. There are HTML and PDF
versions of the documentation available, prebuilt from SGML sources.

The doc/ directory also contains documentation for users of DOMjudge,
namely for jury members (under doc/judge) and teams (under doc/team).

The jury documentation is also available in HTML and PDF format.

The team documentation is available prebuilt in PDF format, but it
contains default/example settings. To include the correct settings for
your local environment, DOMjudge has to be properly configured first,
as parts of the configuration are used in it (e.g. the URL to the team
interface of DOMjudge). A LaTeX installation including the packages
`svn` and `expdlist` is required to rebuild the team documentation.
For more information, see the administrator documentation.

All documentation is also available online at the DOMjudge homepage:
	https://www.domjudge.org/documentation
Please note that this documentation is from the latest stable
release and thus might not apply to your version.

A fresh copy of the repository source tree must first be bootstrapped,
generating the configure script and documentation. This can be done
by running 'make dist', see the online documentation, section
"Developer information" for more details.


Copyright & Licencing
---------------------

DOMjudge is Copyright (c) 2004 - 2018 by the DOMjudge developers and
all respective contributors. The current DOMjudge developers are Jaap
Eldering, Nicky Gerritsen, Keith Johnson, Thijs Kinkhorst and Tobias
Werth; see the administrator's manual for a complete list of
contributors.

DOMjudge, including its documentation, is free software; you can
redistribute it and/or modify it under the terms of the GNU General
Public License as published by the Free Software Foundation; either
version 2, or (at your option) any later version. See the file
COPYING.

Additionally, parts of this system are based on other programs, which
are covered by other copyrights. This will be noted in the files
themselves and these copyrights/attributions can also be found in the
administrator manual.

Various JavaScript libraries/snippets are included under www/js/:
- sorttable.js: copyright Stuart Langridge, licenced under the MIT
  licence, see COPYING.MIT.
- jscolor.js: copyright Jan Odvarko, licenced under the GNU LGPL.
- tabber.js: copyright Patrick Fitzgerald, licenced under the MIT licence.
- Ace editor: licenced under the BSD licence, see COPYING.BSD.
- jQuery: licenced under the MIT licence, see COPYING.MIT.
- jQuery TokenInput: dual licensed under GPL and MIT licences, see COPYING and COPYING.MIT.
- JavaScript Cookie: licenced under the MIT licence, see COPYING.MIT.

The Spyc PHP YAML parser is included, licenced under the MIT licence,
see COPYING.MIT.

GitHub Octicons are copyright 2012-2016 Github.
Font License: SIL OFL 1.1 (http://scripts.sil.org/OFL)
Code License: the MIT licence, see COPYING.MIT.

The default validator from the Kattis problemtools package is
included, licenced under the MIT licence, see COPYING.MIT.

The M4 autoconf macros are licenced under all-permissive and GPL3+
licences; see the respective files under m4/ for details.

Furthermore, a binary version of the dash shell (statically compiled)
is distributed with DOMjudge. This program is copyright by various
people under the BSD licence and a part under the GNU GPL version 2,
see doc/dash.copyright for more details.
Sources can be downloaded from: https://www.domjudge.org/sources/


Contact
-------

The DOMjudge homepage can be found at:
https://www.domjudge.org/

Announcements of new releases are sent to our low volume announcements
mailinglist. Subscription to this list is done via
https://www.domjudge.org/mailman/listinfo/domjudge-announce

The developers can be reached through the mailinglist
domjudge-devel@domjudge.org. You need to be subscribed before
you can post. Information, subscription and archives are available at:
https://www.domjudge.org/mailman/listinfo/domjudge-devel

Some developers and users of DOMjudge linger on the IRC channel
dedicated to DOMjudge on the Freenode network:
server irc.freenode.net, channel #domjudge
