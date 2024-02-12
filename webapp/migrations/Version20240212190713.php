<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240212190713 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional testcase directory to judging run.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE judging_run ADD testcase_dir VARCHAR(256) DEFAULT NULL COMMENT \'The path to the testcase directory on the judgehost.\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE judging_run DROP testcase_dir');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
