<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210327133948 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE INDEX hostname_jobid ON judgetask (hostname(64), jobid)');
        $this->addSql('CREATE INDEX hostname_valid_priority ON judgetask (hostname(64), valid, priority)');
        $this->addSql('CREATE INDEX specific_type ON judgetask (hostname(64), starttime, valid, type, priority, judgetaskid)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP INDEX hostname_jobid ON judgetask');
        $this->addSql('DROP INDEX hostname_valid_priority ON judgetask');
        $this->addSql('DROP INDEX specific_type ON judgetask');
    }
}
