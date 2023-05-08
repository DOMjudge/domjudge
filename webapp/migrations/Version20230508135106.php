<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230508135106 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace jobid with foreign key constraint.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE queuetask RENAME COLUMN jobid TO judgingid');
        $this->addSql('ALTER TABLE queuetask ADD CONSTRAINT FK_45E85FF85D5FEA72 FOREIGN KEY (judgingid) REFERENCES judging (judgingid) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE queuetask DROP FOREIGN KEY FK_45E85FF85D5FEA72');
        $this->addSql('ALTER TABLE queuetask RENAME COLUMN judgingid TO jobid');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
