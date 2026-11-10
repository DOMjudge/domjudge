<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\ScoreboardType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Exception;

/**
 * Recompute the rank cache sort keys for pass-fail contests that use runtime
 * as score tiebreaker.
 *
 * Previously, both updateRankCache and Version20260711120000 always wrote the
 * penalty-time ICPC sort key for pass-fail contests, ignoring the
 * runtime_as_score_tiebreaker contest flag.
 *
 * Note that the scoring logic is deliberately duplicated instead of calling
 * ScoreboardService, so that this migration keeps doing the same thing when
 * the service changes at HEAD.
 */
final class Version20261110230000 extends AbstractMigration
{
    private const SCALE = 9;
    private const ALMOST_INFINITE = '99999999999999999999999';

    public function getDescription(): string
    {
        return 'Recompute rank cache sort keys for contests using runtime as score tiebreaker';
    }

    public function up(Schema $schema): void
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT rc.cid, rc.teamid,
                    rc.points_restricted, rc.totalruntime_restricted,
                    rc.points_public, rc.totalruntime_public
             FROM rankcache rc
             INNER JOIN contest c ON c.cid = rc.cid
             WHERE c.runtime_as_score_tiebreaker = 1
               AND c.scoreboard_type = :passFail',
            ['passFail' => ScoreboardType::PASS_FAIL->value]
        );

        $this->skipIf(empty($rows), 'No rank cache rows use runtime as score tiebreaker.');

        foreach ($rows as $row) {
            $sortKeyRestricted = $this->runtimeSortKey(
                (int)$row['points_restricted'],
                max(0, (int)$row['totalruntime_restricted'])
            );
            $sortKeyPublic = $this->runtimeSortKey(
                (int)$row['points_public'],
                max(0, (int)$row['totalruntime_public'])
            );

            $this->addSql(
                'UPDATE rankcache
                 SET sort_key_restricted = :sortKeyRestricted,
                     sort_key_public = :sortKeyPublic
                 WHERE cid = :cid AND teamid = :teamid',
                [
                    'cid' => (int)$row['cid'],
                    'teamid' => (int)$row['teamid'],
                    'sortKeyRestricted' => $sortKeyRestricted,
                    'sortKeyPublic' => $sortKeyPublic,
                ]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->warnIf(true, 'The recomputed sort keys are kept; they can be rebuilt with domjudge:refresh-cache.');
    }

    public function isTransactional(): bool
    {
        return false;
    }

    private function runtimeSortKey(int $numSolved, int $totalRuntime): string
    {
        return implode(',', [
            $this->sortKeyElement((string)$numSolved),
            $this->sortKeyElement((string)$totalRuntime, ascending: true),
        ]);
    }

    private function sortKeyElement(string $value, bool $ascending = false): string
    {
        $value = bcadd($value, '0', self::SCALE);
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
