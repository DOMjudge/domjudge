<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250620082406 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change comments to reflect entities';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE judging CHANGE max_runtime_for_verdict max_runtime_for_verdict NUMERIC(32, 9) UNSIGNED DEFAULT NULL COMMENT \'The maximum runtime for all runs that resulted in the verdict\'');
        $this->addSql('ALTER TABLE problem CHANGE types types INT NOT NULL COMMENT \'Bitmask of problem types, default is pass-fail.\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE problem CHANGE types types INT NOT NULL COMMENT \'Bitset of problem types, default is pass-fail.\'');
        $this->addSql('ALTER TABLE judging CHANGE max_runtime_for_verdict max_runtime_for_verdict NUMERIC(32, 9) UNSIGNED DEFAULT NULL COMMENT \'The maximum run time for all runs that resulted in the verdict\'');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
