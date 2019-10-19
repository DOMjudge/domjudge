Appendix: Problem format specification
======================================

DOMjudge supports the import and export of problems in a zip-bundle
format.

The base of the format is the `problemarchive.org format`_.

On top, DOMjudge defines a few extensions:
 * ``domjudge-problem.ini`` (required): metadata file, see below.
 * ``problem.{pdf,html,txt}`` (optional): problem statements as
   distributed to participants. The file extension determines any of
   three supported formats. If multiple files matching this pattern are
   available, any one of those will be used.

The file ``domjudge-problem.ini`` contains key-value pairs, one
pair per line, of the form ``key = value``. The ``=`` can
optionally be surrounded by whitespace and the value may be quoted,
which allows it to contain newlines. The following keys are supported
(these correspond directly to the problem settings in the jury web
interface):

 - ``name`` - the problem displayed name
 - ``allow_submit`` - allow submissions to this problem,
   disabling this also makes the problem invisible to teams and public
 - ``allow_judge`` - allow judging of this problem
 - ``timelimit`` - time limit in seconds per test case
 - ``special_run`` - executable id of a special run script
 - ``special_compare`` - executable id of a special compare script
 - ``points`` - number of points for this problem (defaults to 1)
 - ``color`` - CSS color specification for this problem

The basename of the ZIP-file will be used as the problem short name (e.g. "A").
All keys are optional. If they are present, the respective value will be
overwritten; if not present, then the value will not be changed or a default
chosen when creating a new problem. Test data files are added to set of test
cases already present. Thus, one can easily add test cases to a configured
problem by uploading a zip file that contains only testcase files. Any jury
solutions present will be automatically submitted when ``allow_submit`` is
``1`` and there's a team associated with the uploading user.

.. _problemarchive.org format: https://www.problemarchive.org/wiki/index.php/Problem_Format
