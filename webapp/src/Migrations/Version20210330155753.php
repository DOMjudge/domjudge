<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210330155753 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Add hashes to executable files and immutable executables.';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE executable_file ADD hash VARCHAR(32) DEFAULT NULL COMMENT \'hash of the content\'');
        $this->addSql('ALTER TABLE immutable_executable ADD hash VARCHAR(32) DEFAULT NULL COMMENT \'hash of the files\'');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE executable_file DROP hash');
        $this->addSql('ALTER TABLE immutable_executable DROP hash');
    }
}
