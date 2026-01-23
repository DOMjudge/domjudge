<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\ScoreboardType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260123092747 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add penalty time to contest.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contest ADD penalty_time INT UNSIGNED DEFAULT 20 NOT NULL COMMENT \'Penalty time in minutes per wrong submission (if eventually solved)\' AFTER scoreboard_type');

        $penaltyTimeConfig = $this->connection->fetchAssociative('SELECT * FROM configuration WHERE name = :name', ['name' => 'penalty_time']);
        $penaltyTime = $penaltyTimeConfig ? (int)json_decode($penaltyTimeConfig['value'], true) : 20;

        $this->addSql('UPDATE contest SET penalty_time = :penalty_time WHERE scoreboard_type = :scoreboard_type', [
            'penalty_time' => $penaltyTime,
            'scoreboard_type' => ScoreboardType::PASS_FAIL->value,
        ]);

        $this->addSql('UPDATE contest SET penalty_time = 0 WHERE scoreboard_type = :scoreboard_type', [
            'scoreboard_type' => ScoreboardType::SCORE->value,
        ]);

        $this->addSql('DELETE FROM configuration WHERE name = :name', ['name' => 'penalty_time']);
    }

    public function down(Schema $schema): void
    {
        $penaltyTimes = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT penalty_time FROM contest WHERE scoreboard_type = :scoreboard_type',
            ['scoreboard_type' => ScoreboardType::PASS_FAIL->value]
        );

        if (count($penaltyTimes) > 1) {
            throw new \Exception('Cannot migrate down: contests have different penalty times (' . implode(', ', $penaltyTimes) . ')');
        }

        $penaltyTime = $penaltyTimes ? (int)$penaltyTimes[0] : 20;

        if ($penaltyTime !== 20) {
            $this->addSql('INSERT INTO configuration (name, value) VALUES (:name, :value)', [
                'name' => 'penalty_time',
                'value' => json_encode($penaltyTime),
            ]);
        }

        $this->addSql('ALTER TABLE contest DROP penalty_time');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
