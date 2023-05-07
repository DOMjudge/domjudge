<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230506141723 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add team message (teammessage.txt) to judging run output';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE judging_run_output ADD team_message LONGBLOB DEFAULT NULL COMMENT \'Judge message to team(DC2Type:blobtext)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE judging_run_output DROP team_message');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
