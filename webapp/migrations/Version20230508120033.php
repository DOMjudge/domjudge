<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230508120033 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set up cascading delete constraint from submission to judgetask.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE judgetask ADD CONSTRAINT FK_83142B703605A691 FOREIGN KEY (submitid) REFERENCES submission (submitid) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE judgetask DROP FOREIGN KEY FK_83142B703605A691');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
