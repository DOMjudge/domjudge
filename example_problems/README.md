# Test sources for DOMjudge

This directory contains a
[contest archive](https://ccs-specs.icpc.io/master/contest_archive_format)
with several problems and submissions to test the DOMjudge system
for correct functioning after installation.

These files test various parts of the system: mostly whether the
judging backend functions correctly for all languages, but also
whether the submission system can handle some irregular cases. These
contain some stress tests which may break the system and require
manual repair: be careful!

You can either manually verify the results or browse to the "Judging
verifier" page from the admin web-interface to automatically verify
the results using the `@EXPECTED_RESULTS@:` header in the sources.
