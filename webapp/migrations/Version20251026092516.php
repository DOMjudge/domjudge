<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251026092516 extends AbstractMigration
{
    protected const ENTITIES = [
        'clarification' => 'clarid',
        'contest' => 'cid',
        'language' => 'langid',
        'problem' => 'probid',
        'submission' => 'submitid',
        'team' => 'teamid',
        'team_affiliation' => 'affilid',
        'team_category' => 'categoryid',
        'user' => ['userid', 'username'],
    ];

    public function getDescription(): string
    {
        return 'Make auditlog IDs reference external IDs';
    }

    public function up(Schema $schema): void
    {
        // Note: this migration only works for entities that still exist. For others, it will not work but there is nothing we can do
        $this->addSql('ALTER TABLE auditlog CHANGE cid cid VARCHAR(255) DEFAULT NULL COMMENT \'External contest ID associated to this entry\', CHANGE dataid dataid VARCHAR(64) DEFAULT NULL COMMENT \'(External) identifier in reference table\'');
        $this->addSql('UPDATE auditlog INNER JOIN contest ON CAST(contest.cid AS CHAR) = CAST(auditlog.cid AS CHAR) SET auditlog.cid = contest.externalid');
        foreach (static::ENTITIES as $table => $fields) {
            if (!is_array($fields)) {
                $fields = [$fields];
            }
            foreach ($fields as $field) {
                $this->addSql(sprintf(
                    'UPDATE auditlog INNER JOIN %s ON CAST(%s.%s AS CHAR) = CAST(auditlog.dataid AS CHAR) AND auditlog.datatype = "%s" SET auditlog.dataid = %s.externalid',
                    $table, $table, $field, $table, $table
                ));
            }
        }
    }

    public function down(Schema $schema): void
    {
        // Note: this migration only works for entities that still exist. For others, it will not work but there is nothing we can do
        $this->addSql('UPDATE auditlog INNER JOIN contest ON contest.externalid = auditlog.cid SET auditlog.cid = contest.cid');
        foreach (static::ENTITIES as $table => $fields) {
            $field = is_array($fields) ? $fields[0] : $fields;
            $this->addSql(sprintf(
                'UPDATE auditlog INNER JOIN %s ON %s.externalid = auditlog.dataid AND auditlog.datatype = "%s" SET auditlog.dataid = %s.%s',
                $table, $table, $table, $table, $field
            ));
        }
        $this->addSql('ALTER TABLE auditlog CHANGE cid cid INT UNSIGNED DEFAULT NULL COMMENT \'Contest ID associated to this entry\', CHANGE dataid dataid VARCHAR(64) DEFAULT NULL COMMENT \'Identifier in reference table\'');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
