.. _scoring:

Scoring and scoreboard caching implementation
=============================================

DOMjudge supports two scoreboard types: *pass-fail* (ICPC-style) and
*score* (IOI-style partial scoring). The scoreboard type is configured
per contest.

Pass-fail scoring (ICPC-style)
------------------------------

In pass-fail mode, a problem is either accepted or rejected. This follows
the `CCS specification`_, with some additional configuration options.

Key points:

- Teams are sorted by their sortorder first; this is frequently used to group teams into actual participants and other
  groups (e.g. company teams, non-eligible teams, staff, etc.).
- Then teams are sorted ascending by the sum of their problem points. Each correctly solved problem scores a
  pre-defined number of points (integer, by default 1).
- Then teams are sorted descending by either their penalty time (or runtime, can be configured at contest level). The
  penalty time per problem is rounded down to the nearest minute by default, but can be configured to use second
  granularity instead.
- If two teams have the same score, they are sorted by the time of their last accepted submission.

.. _CCS specification: https://ccs-specs.icpc.io/draft/ccs_system_requirements#scoring

Partial scoring (IOI-style)
---------------------------

In scoring mode, teams can earn partial points for partially correct
solutions. This is useful for optimization problems or problems with
multiple independent test groups.

To use partial scoring:

1. Set the contest's *Scoreboard type* to ``score``.
2. Set the problem's type to ``scoring`` (instead of ``pass-fail``).

Scores are determined by testcase groups. Each testcase group can have:

- **Accept score**: Points awarded if all testcases in the group pass.
- **Aggregation type**: How scores from subgroups are combined (``sum``,
  ``min``, ``max``, or ``avg``).
- **On reject continue**: Whether to continue testing other groups even
  if this group fails.

For more complex scoring (e.g., optimization problems where the score
depends on solution quality), a custom output validator can write the
score to ``feedback/score.txt``.

When importing problems via ``problem.yaml``, set::

  type: scoring

Teams keep their best score across multiple submissions. The scoreboard
ranks teams by total score (highest first), with ties broken by
submission time.

Scoreboard caching
------------------

The scoreboard is updated in real-time, which can be a performance bottleneck when there are many teams and problems, so
we employ some caching techniques to speed it up. The scoreboard cache is implemented in two tables, `scorecache` and
`rankcache`.

The ``scorecache`` table is used to store individual scoreboard cells, i.e. information about a single team's score for
a particular problem. Whenever a submission has completed judging, the scorecache is updated for that team and problem.
The table holds all relevant information for the team/problem combination, both for the public and restricted audience
(i.e. jury) who has full information during the freeze.

The ``scorecache`` table is then used to compute the ``rankcache`` table, which holds the aggregated information for each
team in order to quickly compute the rank of each team. This is especially helpful when we want to know the rank of a
subset of teams, e.g. a single team for the single scorerow on the team's overview page. For this, we do first compute
the teams that are definitely better than that single team based on problem points and time (either penalty time or
runtime). Then for all teams that are tied with the current team, we apply the tie breaker (time of last accepted
submission).
