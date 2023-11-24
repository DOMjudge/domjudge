<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231124124901 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sync name of index / foreign key for queuetask.judgingid with model';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE queuetask DROP FOREIGN KEY FK_45E85FF85D5FEA72');
        $this->addSql('DROP INDEX jobid ON queuetask');
        $this->addSql('CREATE INDEX judgingid ON queuetask (judgingid)');
        $this->addSql('ALTER TABLE queuetask ADD CONSTRAINT FK_45E85FF85D5FEA72 FOREIGN KEY (judgingid) REFERENCES judging (judgingid) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE queuetask DROP FOREIGN KEY FK_45E85FF85D5FEA72');
        $this->addSql('DROP INDEX judgingid ON queuetask');
        $this->addSql('CREATE INDEX jobid ON queuetask (judgingid)');
        $this->addSql('ALTER TABLE queuetask ADD CONSTRAINT FK_45E85FF85D5FEA72 FOREIGN KEY (judgingid) REFERENCES judging (judgingid) ON DELETE CASCADE');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
