<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210829113200 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription() : string
    {
        return 'adding debug packages';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE debug_package (debug_package_id INT UNSIGNED AUTO_INCREMENT NOT NULL COMMENT \'Debug Package ID\', judgingid INT UNSIGNED DEFAULT NULL COMMENT \'Judging ID\', judgehostid INT UNSIGNED DEFAULT NULL COMMENT \'Judgehost ID\', filename VARCHAR(255) NOT NULL COMMENT \'Name of the file where we stored the debug package.\', INDEX IDX_9E17399BE0E4FC3E (judgehostid), INDEX judgingid (judgingid), PRIMARY KEY(debug_package_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'Debug packages.\' ');
        $this->addSql('ALTER TABLE debug_package ADD CONSTRAINT FK_9E17399B5D5FEA72 FOREIGN KEY (judgingid) REFERENCES judging (judgingid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE debug_package ADD CONSTRAINT FK_9E17399BE0E4FC3E FOREIGN KEY (judgehostid) REFERENCES judgehost (judgehostid)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE debug_package');
    }
}
