<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210611141202 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription() : string
    {
        return 'Create medal fields';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE contestteamcategoryformedals (cid INT UNSIGNED NOT NULL COMMENT \'Contest ID\', categoryid INT UNSIGNED NOT NULL COMMENT \'Team category ID\', INDEX IDX_40B1F5544B30D9C4 (cid), INDEX IDX_40B1F5549B32FD3 (categoryid), PRIMARY KEY(cid, categoryid)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE contestteamcategoryformedals ADD CONSTRAINT FK_40B1F5544B30D9C4 FOREIGN KEY (cid) REFERENCES contest (cid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contestteamcategoryformedals ADD CONSTRAINT FK_40B1F5549B32FD3 FOREIGN KEY (categoryid) REFERENCES team_category (categoryid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contest ADD medals_enabled TINYINT(1) DEFAULT \'0\' NOT NULL, ADD gold_medals SMALLINT UNSIGNED DEFAULT 4 NOT NULL COMMENT \'Number of gold medals\', ADD silver_medals SMALLINT UNSIGNED DEFAULT 4 NOT NULL COMMENT \'Number of silver medals\', ADD bronze_medals SMALLINT UNSIGNED DEFAULT 4 NOT NULL COMMENT \'Number of bronze medals\'');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE contestteamcategoryformedals');
        $this->addSql('ALTER TABLE contest DROP medals_enabled, DROP gold_medals, DROP silver_medals, DROP bronze_medals');
    }
}
