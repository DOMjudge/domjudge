# Guidelines for contributing to DOMjudge

Thanks for reading our guidelines for contributing to the DOMjudge
project. We welcome all questions, suggestions, bug reports and pull
requests. See below for the preferred ways to report these.

Further below you'll find some hints on where to start for first-time
contributors.

## Reporting bugs and feature requests

We use the [Github issue tracker](https://github.com/DOMjudge/domjudge/issues)
for bug reports, feature requests and anything in between. Try to provide a
clear story, and fill in as much information as possible in our
[Github issue template](https://github.com/DOMjudge/domjudge/blob/master/ISSUE_TEMPLATE.md).
Providing accurate and relevant details will typically speed up the
triaging and debugging process.

Note that Github issues are not a good place to ask questions (see below).

## Questions and problems

Please ask any questions on the
[DOMjudge-devel mailinglist](https://www.domjudge.org/mailman/listinfo/domjudge-devel).
Also if you have a problem and you are not 100% sure this is a bug,
please first ask/discuss it on the mailinglist.

We also have a [Slack Workspace](https://domjudge.org/chat). You're
welcome to join here and ask questions, provide feedback, or just have a chat.
Note that response times may range from a few minutes to hours,
partly because people might be in different timezones.

## Patches / pull requests

Patches or pull requests for bug fixes or feature enhancements are very
much welcomed!

Please try to follow our [coding style guidelines](CODINGSTYLE.md).

If you find that the documentation is incorrect, unclear or missing
something, then reports or, even better, pull requests are very welcome!

Be aware that when accepting new features, we consider whether the
implementation is general and how useful the feature may be to other users;
we try balance this with how much a new feature increases code complexity.
Also consider making your feature optional using a configuration setting.

## Starting to contribute

If you are interested in starting to contribute to DOMjudge, you are
most welcome. The following are some pointers.

First, install and configure the development version of DOMjudge.
Either directly from the git reposity, see the [developer information](https://www.domjudge.org/docs/manual/master/develop.html)
section in the admin manual, or via the [contributor docker image](https://github.com/DOMjudge/domjudge-packaging/tree/master/docker-contributor).
If you follow the admin manual for installation, this is also a great
way to verify that the documentation is clear and complete, and to
report anything that is not.

What to work on? Of course you can start hacking on your own preferred
feature. It's also very helpful to perform an installation of DOMjudge
and see whether our documentation of this can be improved. Or to review
the open issues on the [issue tracker](https://github.com/DOMjudge/domjudge/issues)
and find a way to reproduce them or provide ideas for a solution.

The list of [good first issues](https://github.com/DOMjudge/domjudge/issues?q=is%3Aopen+is%3Aissue+label%3A%22good+first+issue%22).
shows items that are particularly well suites for first-time contributors
to DOMjudge. But of course feel free to start on other items on the issue
tracker as well!

We encourage you to leave a comment on the issue or mention it on Slack that
you're working on something.
