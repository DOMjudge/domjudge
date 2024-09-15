# Coverity settings/configuration

We use Coverity scan for static code analysis. This directory contains
[configuration](https://scan.coverity.com/projects/domjudge?tab=analysis_settings)
that has also been uploaded toCoverity, but is also stored here for
visibility and tracking.

The file `modeling.c` is used to explicitly tell the analysis engine
which code paths terminate execution and related things.

The file `components.csv` lists which components we have configured
and whether they are ignored (for external code) in the analysis.
