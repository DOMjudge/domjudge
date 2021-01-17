# Why does DOMjudge not have partial scoring?

Partial scoring is typically used in IOI style contests, while
DOMjudge has its primary focus on ICPC style contests.

If you're just looking for a system which has partial scoring support,
you may consider [CMS](https://cms-dev.github.io/), as it was
developed for the IOI.

## Logic changes needed to facilitate partial scoring

DOMjudge assumes that a problem is only correctly solved when all
testcases yield a *correct* result. Because of this, judging can be
lazy: stop when one testcase returns a *non*-correct result.
This greatly speeds up judging with the advantage for the participants
that they get their outcome earlier. This feature would have to be
dropped or disabled to support partial scoring.

The fundamental assumption that each submission is either wrong or
correct is also used throughout the scoreboard code, e.g. to optimize
calculating each team's score and storing a cached version of scores
and ranks. Introducing partial scoring would require going through all
this code to find any such hidden assumptions.

As a starter for code that will need to be modified, see:
https://github.com/DOMjudge/domjudge/blob/master/webapp/src/Service/ScoreboardService.php
