<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231124133426 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Split problem text content from problem';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE problem_text_content (probid INT UNSIGNED NOT NULL COMMENT \'Problem ID\', content LONGBLOB NOT NULL COMMENT \'Text content(DC2Type:blobtext)\', PRIMARY KEY(probid)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'Stores contents of problem texts\' ');
        $this->addSql('INSERT INTO problem_text_content (probid, content) SELECT probid, problemtext FROM problem WHERE problemtext IS NOT NULL');
        $this->addSql('ALTER TABLE problem_text_content ADD CONSTRAINT FK_21B6AD6BEF049279 FOREIGN KEY (probid) REFERENCES problem (probid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE problem DROP problemtext');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE problem ADD problemtext LONGBLOB DEFAULT NULL COMMENT \'Problem text in HTML/PDF/ASCII\'');
        $this->addSql('UPDATE problem INNER JOIN problem_text_content USING (probid) SET problem.problemtext = problem_text_content.content');
        $this->addSql('ALTER TABLE problem_text_content DROP FOREIGN KEY FK_21B6AD6BEF049279');
        $this->addSql('DROP TABLE problem_text_content');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
