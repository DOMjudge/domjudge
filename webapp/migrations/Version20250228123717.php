<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250228123717 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add option to restrict a problem to a set of languages.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE problemlanguage (probid INT UNSIGNED NOT NULL COMMENT \'Problem ID\', langid VARCHAR(32) NOT NULL COMMENT \'Language ID (string)\', INDEX IDX_46B150BBEF049279 (probid), INDEX IDX_46B150BB2271845 (langid), PRIMARY KEY(probid, langid)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE problemlanguage ADD CONSTRAINT FK_46B150BBEF049279 FOREIGN KEY (probid) REFERENCES problem (probid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE problemlanguage ADD CONSTRAINT FK_46B150BB2271845 FOREIGN KEY (langid) REFERENCES language (langid) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE problemlanguage DROP FOREIGN KEY FK_46B150BBEF049279');
        $this->addSql('ALTER TABLE problemlanguage DROP FOREIGN KEY FK_46B150BB2271845');
        $this->addSql('DROP TABLE problemlanguage');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
