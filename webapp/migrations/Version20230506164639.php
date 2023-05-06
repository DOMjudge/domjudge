<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230506164639 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'change the value of the event_feed_format configuration key';
    }

    public function up(Schema $schema): void
    {
        $eventFeedFormatConfig = $this->connection->fetchAssociative('SELECT * FROM configuration WHERE name = :name', ['name' => 'event_feed_format']);
        if ($eventFeedFormatConfig) {
            $this->addSql('UPDATE configuration SET value = :value WHERE name = :name', [
                'name' => 'event_feed_format',
                'value' => (int)$eventFeedFormatConfig['value'] === 1 ? '"2022-07"' : '"2020-03"',
            ]);
        }
    }

    public function down(Schema $schema): void
    {
        $eventFeedFormatConfig = $this->connection->fetchAssociative('SELECT * FROM configuration WHERE name = :name', ['name' => 'event_feed_format']);
        if ($eventFeedFormatConfig) {
            $this->addSql('UPDATE configuration SET value = :value WHERE name = :name', [
                'name' => 'event_feed_format',
                'value' => $eventFeedFormatConfig['value'] === '"2022-07"' ? 1 : 0,
            ]);
        }
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
