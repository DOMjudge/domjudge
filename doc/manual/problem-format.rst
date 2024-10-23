Appendix: Problem format specification
======================================

DOMjudge supports the import and export of problems in a zip-bundle
format.

The base of the format is the `ICPC problem package specification`_.

On top, DOMjudge defines a few extensions:
 * ``domjudge-problem.ini`` (optional): metadata file, see below.
 * ``problem.{pdf,html,txt}`` (optional): problem statements as
   distributed to participants. The file extension determines any of
   three supported formats. If multiple files matching this pattern are
   available, any one of those will be used.
 * Annotation with ``@EXPECTED_RESULTS@:`` for jury solutions.
   Use this to annotate the possible outcomes that a submission can have,
   that will be accepted by the Judging verifier.
   This extension is in consideration to be added to the ICPC specification,
   see https://github.com/Kattis/problem-package-format/pull/4.
   Note that, to match with the current ICPC specification,
   the ``@EXPECTED_RESULTS@:`` tag is ignored in
   the four official submission subdirectories,
   but it will work if the tagged solution is in, e.g.,
   ``submissions/mixed-verdicts``, ``submissions/mixed/`` or ``submissions/rejected/``.

   .. literalinclude:: ../../example_problems/boolfind/submissions/mixed-verdicts/boolfind-test-random.py
      :language: python
      :caption: *Example jury solution with multiple outcomes (Shortened)*
      :lines: -10

The file ``domjudge-problem.ini`` contains key-value pairs, one
pair per line, of the form ``key = value``. The ``=`` can
optionally be surrounded by whitespace and the value may be quoted,
which allows it to contain newlines. The following keys are supported
(these correspond directly to the problem settings in the jury web
interface):

 - ``name`` - the problem displayed name
 - ``allow_submit`` - whether to allow submissions to this problem,
   disabling this also makes the problem invisible to teams and public
 - ``allow_judge`` - whether to allow judging of this problem
 - ``timelimit`` - time limit in seconds per test case
 - ``special_run`` - executable id of a special run script
 - ``special_compare`` - executable id of a special compare script
 - ``points`` - number of points for this problem (defaults to 1)
 - ``color`` - CSS color specification for this problem
 - ``externalid`` - the external id of the problem
 - ``short-name`` - the short name of the problem 

The basename of the ZIP-file will be used as the problem external id and short
name (e.g. "A"). All keys are optional. If they are present, the respective
value will be overwritten; if not present, then the value will not be changed
or a default chosen when creating a new problem. Test data files are added to
set of test cases already present. Thus, one can easily add test cases to a
configured problem by uploading a zip file that contains only testcase files.
Any jury solutions present will be automatically submitted when ``allow_submit``
is ``1`` and there's a team associated with the uploading user.

.. _ICPC problem package specification: https://icpc.io/problem-package-format/spec/legacy-icpc
