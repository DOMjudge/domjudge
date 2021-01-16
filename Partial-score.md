# Why does DOMjudge not have partial scoring?

Partial scoring is typicly used in IOI style contests, DOMjudge has its primary focus on ICPC style contests.

If you're just looking for a system which has this one could consider:
[CMS](https://cms-dev.github.io/)

## Logic changes needed to facilitate partial scoring
DOMjudge assumes that a problem is only correctly solved when all testcases yield a *correct* result. Because of this judging can be lazy as one can stop when one testcase returns a *non*-correct result. This greatly speeds up judging with the advantage for the participants that they get their outcome earlier. To consider partial scoring one has to drop this requirement and would need to store the result on every testcased and add a score per testcases. The concept of rejudging also becomes more problematic as DOMjudge assumes that it should only rejudge the latest submission whereas for an IOI style contest where one rates based on the number of testcases one would typicly consider the best submission with the highest score.
