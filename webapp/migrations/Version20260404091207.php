<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260404091207 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow storing the testcase .in/.ans validators.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE problem ADD input_validator VARCHAR(32) DEFAULT NULL COMMENT \'Executable ID (string)\', ADD answer_validator VARCHAR(32) DEFAULT NULL COMMENT \'Executable ID (string)\'');
        $this->addSql('ALTER TABLE problem ADD CONSTRAINT FK_D7E7CCC8B88B7970 FOREIGN KEY (input_validator) REFERENCES executable (execid) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE problem ADD CONSTRAINT FK_D7E7CCC8C75D4F1E FOREIGN KEY (answer_validator) REFERENCES executable (execid) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_D7E7CCC8B88B7970 ON problem (input_validator)');
        $this->addSql('CREATE INDEX IDX_D7E7CCC8C75D4F1E ON problem (answer_validator)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE problem DROP FOREIGN KEY FK_D7E7CCC8B88B7970');
        $this->addSql('ALTER TABLE problem DROP FOREIGN KEY FK_D7E7CCC8C75D4F1E');
        $this->addSql('DROP INDEX IDX_D7E7CCC8B88B7970 ON problem');
        $this->addSql('DROP INDEX IDX_D7E7CCC8C75D4F1E ON problem');
        $this->addSql('ALTER TABLE problem DROP input_validator, DROP answer_validator');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
