<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240604202419 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set external ID\'s for entities that don\'t have them';
    }

    public function up(Schema $schema): void
    {
        $djPrefixed = [
            'contest' => 'cid',
            'language' => 'langid',
            'problem' => 'probid',
            'team' => 'teamid',
            'team_affiliation' => 'affilid',
            'team_category' => 'categoryid',
        ];
        $notPrefixed = [
            'clarification' => 'clarid',
            'submission' => 'submitid',
            'user' => 'username',
        ];

        foreach ($djPrefixed as $table => $column) {
            $this->setExternalIds($table, $column, 'dj-');
        }

        foreach ($notPrefixed as $table => $column) {
            $this->setExternalIds($table, $column);
        }
    }

    protected function setExternalIds(string $table, string $column, string $prefix = ''): void
    {
        $entries = $this->connection->fetchAllAssociative("SELECT $column FROM $table WHERE externalid IS NULL");
        foreach ($entries as $entry) {
            $newExternalId = $prefix . $entry[$column];
            // Check if any entity already has this external ID
            $existingEntity = $this->connection->fetchAssociative("SELECT externalid FROM $table WHERE externalid = :externalid", ['externalid' => $newExternalId]);
            $humanReadableTable = ucfirst(str_replace('_', ' ', $table));
            $this->abortIf((bool)$existingEntity, "$humanReadableTable entity with external ID $newExternalId already exists, manually set a different external ID");
            $this->addSql("UPDATE $table SET externalid = :externalid WHERE $column = :$column", [
                'externalid' => $newExternalId,
                $column => $entry[$column],
            ]);
        }
    }

    public function down(Schema $schema): void
    {
        // No down migration needed
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
