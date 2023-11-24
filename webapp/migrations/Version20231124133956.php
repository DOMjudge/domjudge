<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231124133956 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add version reference to judgetask.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE judgetask ADD versionid INT UNSIGNED DEFAULT NULL COMMENT \'Version ID\'');
        $this->addSql('ALTER TABLE judgetask ADD CONSTRAINT FK_83142B704034DFAF FOREIGN KEY (versionid) REFERENCES version (versionid) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_83142B704034DFAF ON judgetask (versionid)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE judgetask DROP FOREIGN KEY FK_83142B704034DFAF');
        $this->addSql('DROP INDEX IDX_83142B704034DFAF ON judgetask');
        $this->addSql('ALTER TABLE judgetask DROP versionid');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
