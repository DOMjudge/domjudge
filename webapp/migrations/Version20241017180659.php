<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241017180659 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE visualization (visualization_id INT UNSIGNED AUTO_INCREMENT NOT NULL COMMENT \'Visualization ID\', judgingid INT UNSIGNED DEFAULT NULL COMMENT \'Judging ID\', judgehostid INT UNSIGNED DEFAULT NULL COMMENT \'Judgehost ID\', testcaseid INT UNSIGNED DEFAULT NULL COMMENT \'Testcase ID\', filename VARCHAR(255) NOT NULL COMMENT \'Name of the file where we stored the visualization.\', INDEX IDX_E0936C40E0E4FC3E (judgehostid), INDEX IDX_E0936C40D360BB2B (testcaseid), INDEX judgingid (judgingid), PRIMARY KEY(visualization_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'Team output visualization.\' ');
        $this->addSql('ALTER TABLE visualization ADD CONSTRAINT FK_E0936C405D5FEA72 FOREIGN KEY (judgingid) REFERENCES judging (judgingid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE visualization ADD CONSTRAINT FK_E0936C40E0E4FC3E FOREIGN KEY (judgehostid) REFERENCES judgehost (judgehostid) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE visualization ADD CONSTRAINT FK_E0936C40D360BB2B FOREIGN KEY (testcaseid) REFERENCES testcase (testcaseid) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE visualization DROP FOREIGN KEY FK_E0936C405D5FEA72');
        $this->addSql('ALTER TABLE visualization DROP FOREIGN KEY FK_E0936C40E0E4FC3E');
        $this->addSql('ALTER TABLE visualization DROP FOREIGN KEY FK_E0936C40D360BB2B');
        $this->addSql('DROP TABLE visualization');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
