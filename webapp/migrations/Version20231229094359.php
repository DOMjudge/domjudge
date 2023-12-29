<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231229094359 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add potential first to solve to scorecache.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE scorecache ADD is_potential_first_to_solve TINYINT(1) DEFAULT 0 NOT NULL COMMENT \'Is this potentially the first solution to this problem?\' AFTER is_first_to_solve');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE scorecache DROP is_potential_first_to_solve');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
