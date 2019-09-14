<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190914080214 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'add table to link categories to contests';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE contestteamcategory (cid INT UNSIGNED NOT NULL COMMENT \'Contest ID\', categoryid INT UNSIGNED NOT NULL COMMENT \'Team category ID\', INDEX IDX_51A2DDC44B30D9C4 (cid), INDEX IDX_51A2DDC49B32FD3 (categoryid), PRIMARY KEY(cid, categoryid)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE contestteamcategory ADD CONSTRAINT FK_51A2DDC44B30D9C4 FOREIGN KEY (cid) REFERENCES contest (cid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contestteamcategory ADD CONSTRAINT FK_51A2DDC49B32FD3 FOREIGN KEY (categoryid) REFERENCES team_category (categoryid) ON DELETE CASCADE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE contestteamcategory');
    }
}
