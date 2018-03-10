# Guidelines for contributing to DOMjudge

Thanks for reading our guidelines for contributing to the DOMjudge
project. We welcome questions, suggestions, bug reports and pull
requests. See below for the preferred ways to report these.


## Questions and problems

Please ask any questions on the
[DOMjudge-devel mailinglist](https://www.domjudge.org/mailman/listinfo/domjudge-devel).
Also if you have a problem and you are not 100% sure this is a bug,
please first ask/discuss it on the mailinglist.

We also have an IRC channel on the Freenode network: server
irc.freenode.net, channel #domjudge. You're welcome to join here and ask
questions, provide feedback, or just have a chat. Note that response times
may range from a few minutes to hours.

When reporting a problem, please provide the following details:
- the version of DOMjudge you are using
- your operating system (Linux distribution) and version
- the exact error message you get, logfiles, etc.
- the steps you took that led to the error
Providing accurate and relevant details will typically speed up the
triaging and debugging process.

## Reporting bugs and feature requests

We use the [github issue tracker](https://github.com/DOMjudge/domjudge/issues)
for bug reports, feature requests and anything in between. Try to provide a
clear story, and when reporting a bug, as much as possible of the details
above.


## Patches / pull requests

Patches or pull requests for bug fixes or feature enhancements are very
much welcomed!

We have [coding guidelines](CODINGSTYLE); although we do not follow these
strictly, please try to keep your patches in line with these guidelines and
the respective code.

Be aware that when accepting new features, we consider whether the
implementation is general and how useful the feature may be to other users;
we try balance this with how much a new feature increases code complexity.
Also consider making your feature optional using a configuration setting.
Although we are very interested to hear what modifications you made to a
local installation of DOMjudge, these changes do not always make sense in a
more general context.

If you find that the documentation is incorrect, unclear or missing
something, then reports or, even better, pull requests are very welcome!


## Starting to contribute

If you are interested in starting to contribute to DOMjudge, then the
following are some pointers.

First, install and configure the development version of DOMjudge.
Either directly from the git reposity, see the
[developer information](https://www.domjudge.org/docs/admin-manual-10.html)
section in the admin manual, or via the
(contributor docker image)[https://github.com/DOMjudge/domjudge-packaging/tree/master/docker-contributor].
If you follow the admin manual for installation, this is also a great way
to verify that the documentation is clear and complete, and to report
anything that is not.

Start hacking on your own preferred feature(s), or check the list of
(good first issues)[https://github.com/DOMjudge/domjudge/issues?q=is%3Aopen+is%3Aissue+label%3A%22good+first+issue%22].
We encourage you to contact us to let us know you're working on a feature.
That way we can suggest good ways to implement it, and whether it would
be useful for inclusion in the project. See also submitting pull requests
above.
