<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231124123934 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Synchronize table comments with models';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE queuetask CHANGE judgingid judgingid INT UNSIGNED DEFAULT NULL COMMENT \'Judging ID\'');
        $this->addSql('ALTER TABLE submission CHANGE import_error import_error VARCHAR(255) DEFAULT NULL COMMENT \'The error message for submissions which got an error during shadow importing.\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE queuetask CHANGE judgingid judgingid INT UNSIGNED DEFAULT NULL COMMENT \'All queuetasks with the same jobid belong together.\'');
        $this->addSql('ALTER TABLE submission CHANGE import_error import_error VARCHAR(255) DEFAULT NULL COMMENT \'If this submission was imported during shadowing but had an error while doing so, the error message.\'');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
