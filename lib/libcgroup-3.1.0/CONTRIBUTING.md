How to Contribute to the libcgroup Project
===============================================================================
https://github.com/libcgroup/libcgroup

This document outlines the steps to help you contribute to the libcgroup project.
As with the libcgroup code itself, the process is a work in progress.
Improvements and suggestions are welcome and encouraged.

## Interacting with the Community

> "When you are kind to others, it not only changes you, it changes the
> world." - Harold Kushner

The libcgroup project strives to be an inclusive and welcoming place.
If you interact with the libcgroup project, we request that you treat others
with dignity and respect.  Failure to do so will result in a warning.  In
extreme cases, we reserve the right to block the individual from the project.

Examples of inappropriate behavior includes: profane, abusive, or prejudicial
language directed at another person, vandalism (e.g. GitHub issue/PR "litter"),
or spam.

## Test Your Code Using Existing Tests

The libcgroup project utilizes unit and functional tests.  These tests must
successfully pass prior to a commit being merged.

You can run both the unit and functional tests with the following command:

	# make check

You can invoke only the unit tests with the following commands:

	# cd tests/gunit
	# make check

If there are unit test failures, running the unit tests outside of the
automake framework will provide more information.

	# cd tests/gunit
	# ./gtest

You can invoke only the functional tests with the following commands:

	# cd tests/ftests
	# make check

Note that the functional tests can be run within a container or directly on
your system.  For the containerized tests, libcgroup utilizes LXC/LXD
containers.  If your system or distro doesn't support LXC/LXD, you can
utilize the continuous integration infrastructure to test your changes.  A
successful continuous integration run is required for each pull request.

Many tests can also be run outside of a container.  Use caution with these
tests though, as they will modify your host's cgroup hierarchy.  This could
significantly and negatively affect your system.

We encourage utilizing a VM for libcgroup development work.  The continuous
integration suite utilizes the latest Ubuntu LTS.

To run the containerized tests only:

	# cd tests/ftests
	# ./ftests.sh

To run the non-containerized tests only:

	# cd tests/ftests
	# ./ftests-nocontainer.sh

After the run is complete, the ftests.sh.log and ftests-nocontainer.sh.log
contain the full debug log for each run.

## Add New Tests for New Functionality

The libcgroup project utilizes automated tests, code coverage, and continuous
integration to maintain a high level of code quality.  Any pull requests that
add functionality or significantly change existing code should include
additional tests to verify the proper operation of the proposed changes.  Note
that functional tests are preferred over unit tests.

The continuous integration tools run the automated tests and automatically
gather code coverage numbers.  Pull requests that cause the code coverage
numbers to decrease are strongly discouraged.

## Explain Your Work

At the top of every patch you should include a description of the problem you
are trying to solve, how you solved it, and why you chose the solution you
implemented.  If you are submitting a bug fix, it is also incredibly helpful
if you can describe/include a reproducer for the problem in the description as
well as instructions on how to test for the bug and verify that it has been
fixed.

## Sign Your Work

The sign-off is a simple line at the end of the patch description, which
certifies that you wrote it or otherwise have the right to pass it on as an
open-source patch.  The "Developer's Certificate of Origin" pledge is taken
from the Linux Kernel and the rules are pretty simple:

	Developer's Certificate of Origin 1.1

	By making a contribution to this project, I certify that:

	(a) The contribution was created in whole or in part by me and I
	    have the right to submit it under the open source license
	    indicated in the file; or

	(b) The contribution is based upon previous work that, to the best
	    of my knowledge, is covered under an appropriate open source
	    license and I have the right under that license to submit that
	    work with modifications, whether created in whole or in part
	    by me, under the same open source license (unless I am
	    permitted to submit under a different license), as indicated
	    in the file; or

	(c) The contribution was provided directly to me by some other
	    person who certified (a), (b) or (c) and I have not modified
	    it.

	(d) I understand and agree that this project and the contribution
	    are public and that a record of the contribution (including all
	    personal information I submit with it, including my sign-off) is
	    maintained indefinitely and may be redistributed consistent with
	    this project or the open source license(s) involved.

... then you just add a line to the bottom of your patch description, with
your real name, saying:

	Signed-off-by: Random J Developer <random@developer.example.org>

You can add this to your commit description in `git` with `git commit -s`

## Submitting Patches

libcgroup was initially hosted on Sourceforge and at that time only accepted
patches via the mailing list.  In 2018, libcgroup was moved to github and now
accepts patches via email or github pull request.  Over time the libcgroup
project will likely fully transition to gitub pull requests and issues.

### Post Your Patches Upstream

The sections below explain how to contribute via either method. Please read
each step and perform all steps that apply to your chosen contribution method.

### Submitting via Email

Depending on how you decided to work with the libcgroup code base and what
tools you are using there are different ways to generate your patch(es).
However, regardless of what tools you use, you should always generate your
patches using the "unified" diff/patch format and the patches should always
apply to the libcgroup source tree using the following command from the top
directory of the libcgroup sources:

	# patch -p1 < changes.patch

If you are not using git, stacked git (stgit), or some other tool which can
generate patch files for you automatically, you may find the following command
helpful in generating patches, where "libcgroup.orig/" is the unmodified
source code directory and "libcgroup/" is the source code directory with your
changes:

	# diff -purN libcgroup.orig/ libcgroup/

When in doubt please generate your patch and try applying it to an unmodified
copy of the libcgroup sources; if it fails for you, it will fail for the rest
of us.

Finally, you will need to email your patches to the mailing list so they can
be reviewed and potentially merged into the main libcgroup repository.  When
sending patches to the mailing list it is important to send your email in text
form, no HTML mail please, and ensure that your email client does not mangle
your patches.  It should be possible to save your raw email to disk and apply
it directly to the libcgroup source code; if that fails then you likely have
a problem with your email client.  When in doubt try a test first by sending
yourself an email with your patch and attempting to apply the emailed patch to
the libcgrup repository; if it fails for you, it will fail for the rest of
us trying to test your patch and include it in the main libcgroup repository.

### Submitting via GitHub Pull Requests

See [this guide](https://help.github.com/en/github/collaborating-with-issues-and-pull-requests/creating-a-pull-request) if you've never done this before.

