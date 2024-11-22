<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241122144232 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add many to many relation between contest and langs.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE contestlanguage (cid INT UNSIGNED NOT NULL COMMENT \'Contest ID\', langid VARCHAR(32) NOT NULL COMMENT \'Language ID (string)\', INDEX IDX_ADCB43234B30D9C4 (cid), INDEX IDX_ADCB43232271845 (langid), PRIMARY KEY(cid, langid)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE contestlanguage ADD CONSTRAINT FK_ADCB43234B30D9C4 FOREIGN KEY (cid) REFERENCES contest (cid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contestlanguage ADD CONSTRAINT FK_ADCB43232271845 FOREIGN KEY (langid) REFERENCES language (langid) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contestlanguage DROP FOREIGN KEY FK_ADCB43234B30D9C4');
        $this->addSql('ALTER TABLE contestlanguage DROP FOREIGN KEY FK_ADCB43232271845');
        $this->addSql('DROP TABLE contestlanguage');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
