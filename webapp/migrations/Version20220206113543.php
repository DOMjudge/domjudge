<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220206113543 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription() : string
    {
        return 'Rename some indices for Doctrine upgrade';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE contestteamcategoryformedals DROP FOREIGN KEY FK_40B1F5544B30D9C4');
        $this->addSql('ALTER TABLE contestteamcategoryformedals DROP FOREIGN KEY FK_40B1F5549B32FD3');
        $this->addSql('DROP INDEX IDX_40b1f5544b30d9c4 ON contestteamcategoryformedals');
        $this->addSql('CREATE INDEX IDX_CC3496DE4B30D9C4 ON contestteamcategoryformedals (cid)');
        $this->addSql('DROP INDEX IDX_40b1f5549b32fd3 ON contestteamcategoryformedals');
        $this->addSql('CREATE INDEX IDX_CC3496DE9B32FD3 ON contestteamcategoryformedals (categoryid)');
        $this->addSql('ALTER TABLE contestteamcategoryformedals ADD CONSTRAINT FK_40B1F5544B30D9C4 FOREIGN KEY (cid) REFERENCES contest (cid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contestteamcategoryformedals ADD CONSTRAINT FK_40B1F5549B32FD3 FOREIGN KEY (categoryid) REFERENCES team_category (categoryid) ON DELETE CASCADE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE contestteamcategoryformedals DROP FOREIGN KEY FK_CC3496DE4B30D9C4');
        $this->addSql('ALTER TABLE contestteamcategoryformedals DROP FOREIGN KEY FK_CC3496DE9B32FD3');
        $this->addSql('DROP INDEX IDX_cc3496de4b30d9c4 ON contestteamcategoryformedals');
        $this->addSql('CREATE INDEX IDX_40B1F5544B30D9C4 ON contestteamcategoryformedals (cid)');
        $this->addSql('DROP INDEX IDX_cc3496de9b32fd3 ON contestteamcategoryformedals');
        $this->addSql('CREATE INDEX IDX_40B1F5549B32FD3 ON contestteamcategoryformedals (categoryid)');
        $this->addSql('ALTER TABLE contestteamcategoryformedals ADD CONSTRAINT FK_CC3496DE4B30D9C4 FOREIGN KEY (cid) REFERENCES contest (cid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contestteamcategoryformedals ADD CONSTRAINT FK_CC3496DE9B32FD3 FOREIGN KEY (categoryid) REFERENCES team_category (categoryid) ON DELETE CASCADE');
    }
}
