<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250302114442 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Associate internal errors with judging run if possible.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE internal_error ADD runid INT UNSIGNED DEFAULT NULL COMMENT \'Run ID\'');
        $this->addSql('ALTER TABLE internal_error ADD CONSTRAINT FK_518727D8A5788799 FOREIGN KEY (runid) REFERENCES judging_run (runid) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_518727D8A5788799 ON internal_error (runid)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE internal_error DROP FOREIGN KEY FK_518727D8A5788799');
        $this->addSql('DROP INDEX IDX_518727D8A5788799 ON internal_error');
        $this->addSql('ALTER TABLE internal_error DROP runid');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
