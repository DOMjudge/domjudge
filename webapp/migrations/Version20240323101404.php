<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240323101404 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename problem text to statement';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE problem_statement_content (probid INT UNSIGNED NOT NULL COMMENT \'Problem ID\', content LONGBLOB NOT NULL COMMENT \'Statement content(DC2Type:blobtext)\', PRIMARY KEY(probid)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'Stores contents of problem statement\' ');
        $this->addSql('ALTER TABLE problem_statement_content ADD CONSTRAINT FK_8A666422EF049279 FOREIGN KEY (probid) REFERENCES problem (probid) ON DELETE CASCADE');
        $this->addSql('INSERT INTO problem_statement_content (probid, content) SELECT probid, content FROM problem_text_content');
        $this->addSql('DROP TABLE problem_text_content');
        $this->addSql('ALTER TABLE problem CHANGE problemtext_type problemstatement_type VARCHAR(4) DEFAULT NULL COMMENT \'File type of problem statement\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE problem_text_content (probid INT UNSIGNED NOT NULL COMMENT \'Problem ID\', content LONGBLOB NOT NULL COMMENT \'Text content(DC2Type:blobtext)\', PRIMARY KEY(probid)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'Stores contents of problem texts\' ');
        $this->addSql('ALTER TABLE problem_text_content ADD CONSTRAINT FK_21B6AD6BEF049279 FOREIGN KEY (probid) REFERENCES problem (probid) ON DELETE CASCADE');
        $this->addSql('INSERT INTO problem_text_content (probid, content) SELECT probid, content FROM problem_statement_content');
        $this->addSql('DROP TABLE problem_statement_content');
        $this->addSql('ALTER TABLE problem CHANGE problemstatement_type problemtext_type VARCHAR(4) DEFAULT NULL COMMENT \'File type of problem text\'');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
