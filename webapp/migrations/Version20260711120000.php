<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\ScoreboardType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Exception;

/**
 * Repair the scoreboard caches of databases that predate the sort keys.
 *
 * Two defects can be present in an upgraded database:
 *
 * Previously, we disallowed negative numbers on the scoreboard, submissions
 * made before the contest start (in practice only by the jury during problem
 * import) were stored with a negative contest-relative solve time.
 * Because the rank cache sums the solve times of all problems of a team, a
 * single such row makes every later rank cache update of that team fail with
 * "No negative values allowed in score key element".
 * We now clamp those to the contest start when calculating a score row, but
 * rows written by the old code still hold the negative value.
 *
 * Secondly, the migration that introduced the sort keys only added the
 * columns; it never populated them.
 * Rows written before the upgrade therefore have an empty sort key, and since
 * the scoreboard is ordered by it, all those teams compare equal.
 *
 * Both are fixed here by clamping the stored solve times and by recomputing
 * the rank cache from the (repaired) score cache.
 *
 * Note, that the scoring logic is deliberately duplicated instead of calling
 * ScoreboardService, so that this migration keeps doing the same thing when
 * the service changes at HEAD.
 */
final class Version20260711120000 extends AbstractMigration
{
    private const SCALE = 9;
    private const ALMOST_INFINITE = '99999999999999999999999';

    public function getDescription(): string
    {
        return 'Clamp negative solve times in the score cache and backfill the rank cache sort keys';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE scorecache SET solvetime_restricted = 0 WHERE solvetime_restricted < 0');
        $this->addSql('UPDATE scorecache SET solvetime_public = 0 WHERE solvetime_public < 0');

        $scoreInSeconds = $this->scoreInSeconds();
        $teamPenalties  = $this->connection->fetchAllKeyValue('SELECT teamid, penalty FROM team');

        // Handle one contest at a time to keep the memory usage bounded on long-running sites.
        $contests = $this->connection->fetchAllAssociative(
            'SELECT cid, scoreboard_type, penalty_time FROM contest'
        );
        foreach ($contests as $contest) {
            $cid            = (int)$contest['cid'];
            $scoreboardType = ScoreboardType::from($contest['scoreboard_type']);
            $penaltyTime    = (int)$contest['penalty_time'];

            // A team without any score cache row still needs a rank cache row with a valid sort key.
            $rows = [];
            foreach ($this->connection->fetchFirstColumn(
                'SELECT teamid FROM rankcache WHERE cid = :cid
                 UNION
                 SELECT teamid FROM scorecache WHERE cid = :cid',
                ['cid' => $cid]
            ) as $teamid) {
                $rows[(int)$teamid] = $this->emptyRow((int)($teamPenalties[$teamid] ?? 0));
            }

            // Only problems that are part of the contest count, exactly like the rank cache update does.
            $scoreCache = $this->connection->fetchAllAssociative(
                'SELECT sc.teamid, cp.points,
                        sc.is_correct_restricted, sc.solvetime_restricted, sc.submissions_restricted,
                        sc.runtime_restricted, sc.score_restricted,
                        sc.is_correct_public, sc.solvetime_public, sc.submissions_public,
                        sc.runtime_public, sc.score_public
                 FROM scorecache sc
                 INNER JOIN contestproblem cp ON cp.cid = sc.cid AND cp.probid = sc.probid
                 WHERE sc.cid = :cid',
                ['cid' => $cid]
            );

            foreach ($scoreCache as $cell) {
                $teamid = (int)$cell['teamid'];
                $row    = &$rows[$teamid];

                foreach (['restricted', 'public'] as $variant) {
                    $score = (string)$cell['score_' . $variant];

                    // For scoring contests partial scores count even when the problem is not solved.
                    if ($scoreboardType === ScoreboardType::SCORE) {
                        $row['score'][$variant] = bcadd($row['score'][$variant], $score, self::SCALE);
                    }

                    if (!(bool)$cell['is_correct_' . $variant]) {
                        continue;
                    }

                    // Negative solve times are clamped here as well, as the UPDATEs above are only
                    // queued and have not run against the rows we just read.
                    $solveTime = $this->scoreTime(max(0.0, (float)$cell['solvetime_' . $variant]), $scoreInSeconds);
                    $penalty   = ((int)$cell['submissions_' . $variant] - 1) * $penaltyTime * ($scoreInSeconds ? 60 : 1);

                    $row['numPoints'][$variant]         += (int)$cell['points'];
                    $row['totalTime'][$variant]         += $solveTime + $penalty;
                    $row['totalRuntime'][$variant]      += (int)$cell['runtime_' . $variant];
                    $row['timeOfLastCorrect'][$variant]  = max($row['timeOfLastCorrect'][$variant], $solveTime);

                    if ($scoreboardType !== ScoreboardType::SCORE) {
                        $row['score'][$variant] = bcadd($row['score'][$variant], $score, self::SCALE);
                    }
                }
                unset($row);
            }

            foreach ($rows as $teamid => $row) {
                $sortKey = [];
                foreach (['restricted', 'public'] as $variant) {
                    $sortKey[$variant] = match ($scoreboardType) {
                        ScoreboardType::PASS_FAIL => $this->icpcSortKey(
                            $row['numPoints'][$variant],
                            $row['totalTime'][$variant],
                            $row['timeOfLastCorrect'][$variant]
                        ),
                        ScoreboardType::SCORE => $this->sortKeyElement($row['score'][$variant]),
                    };
                }

                $this->addSql(
                    'REPLACE INTO rankcache (cid, teamid,
                        points_restricted, totaltime_restricted, totalruntime_restricted, score_restricted,
                        points_public, totaltime_public, totalruntime_public, score_public,
                        sort_key_restricted, sort_key_public)
                     VALUES (:cid, :teamid,
                        :pointsRestricted, :totalTimeRestricted, :totalRuntimeRestricted, :scoreRestricted,
                        :pointsPublic, :totalTimePublic, :totalRuntimePublic, :scorePublic,
                        :sortKeyRestricted, :sortKeyPublic)',
                    [
                        'cid' => $cid,
                        'teamid' => $teamid,
                        'pointsRestricted' => $row['numPoints']['restricted'],
                        'totalTimeRestricted' => $row['totalTime']['restricted'],
                        'totalRuntimeRestricted' => $row['totalRuntime']['restricted'],
                        'scoreRestricted' => $row['score']['restricted'],
                        'pointsPublic' => $row['numPoints']['public'],
                        'totalTimePublic' => $row['totalTime']['public'],
                        'totalRuntimePublic' => $row['totalRuntime']['public'],
                        'scorePublic' => $row['score']['public'],
                        'sortKeyRestricted' => $sortKey['restricted'],
                        'sortKeyPublic' => $sortKey['public'],
                    ]
                );
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->warnIf(true, 'The repaired scoreboard caches are kept; they can be rebuilt with domjudge:refresh-cache.');
    }

    public function isTransactional(): bool
    {
        return false;
    }

    /**
     * @return array{numPoints: int[], totalTime: int[], totalRuntime: int[], timeOfLastCorrect: int[], score: string[]}
     */
    private function emptyRow(int $teamPenalty): array
    {
        return [
            'numPoints' => ['restricted' => 0, 'public' => 0],
            'totalTime' => ['restricted' => $teamPenalty, 'public' => $teamPenalty],
            'totalRuntime' => ['restricted' => 0, 'public' => 0],
            'timeOfLastCorrect' => ['restricted' => 0, 'public' => 0],
            'score' => ['restricted' => '0', 'public' => '0'],
        ];
    }

    private function scoreInSeconds(): bool
    {
        $value = $this->connection->fetchOne(
            "SELECT value FROM configuration WHERE name = 'score_in_seconds'"
        );
        // Absent means the default from etc/db-config.yaml, which is false.
        return $value === false ? false : (bool)json_decode((string)$value, true);
    }

    private function scoreTime(float $time, bool $scoreInSeconds): int
    {
        return $scoreInSeconds ? (int)$time : (int)floor($time / 60);
    }

    private function icpcSortKey(int $numSolved, int $totalTime, int $timeOfLastSolved): string
    {
        return implode(',', [
            $this->sortKeyElement((string)$numSolved),
            $this->sortKeyElement((string)$totalTime, ascending: true),
            $this->sortKeyElement((string)$timeOfLastSolved, ascending: true),
        ]);
    }

    private function sortKeyElement(string $value, bool $ascending = false): string
    {
        $value = bcadd($value, '0', self::SCALE);
        // Refuse to write a malformed key: the whole point of this migration is to repair them.
        // A negative total time is reachable via a negative team penalty, which nothing validates.
        if (bccomp($value, self::ALMOST_INFINITE, self::SCALE) > 0) {
            throw new Exception("Value $value is too large to convert to a score key element.");
        }
        if (str_starts_with($value, '-')) {
            throw new Exception("No negative values allowed in score key element, got $value.");
        }

        if ($ascending) {
            $value = bcsub(self::ALMOST_INFINITE, $value, self::SCALE);
        }
        return str_pad($value, 33, '0', STR_PAD_LEFT);
    }
}
