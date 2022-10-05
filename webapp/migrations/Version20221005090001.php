<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221005090001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE external_judgement CHANGE jury_member jury_member VARCHAR(255) DEFAULT NULL COMMENT \'Name of user who verified the result / difference\'');
        $this->addSql('ALTER TABLE judgetask CHANGE compile_config compile_config LONGTEXT DEFAULT \'NULL\' COLLATE `utf8mb4_bin` COMMENT \'The compile config as JSON-blob.\', CHANGE run_config run_config LONGTEXT DEFAULT \'NULL\' COLLATE `utf8mb4_bin` COMMENT \'The run config as JSON-blob.\', CHANGE compare_config compare_config LONGTEXT DEFAULT \'NULL\' COLLATE `utf8mb4_bin` COMMENT \'The compare config as JSON-blob.\'');
        $this->addSql('ALTER TABLE team CHANGE judging_last_started judging_last_started NUMERIC(32, 9) UNSIGNED DEFAULT NULL COMMENT \'Start time of last judging for prioritization\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE team CHANGE judging_last_started judging_last_started NUMERIC(32, 9) UNSIGNED DEFAULT NULL COMMENT \'Start time of last judging for priorization\'');
        $this->addSql('ALTER TABLE judgetask CHANGE compile_config compile_config LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin` COMMENT \'The compile config as JSON-blob.\', CHANGE run_config run_config LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin` COMMENT \'The run config as JSON-blob.\', CHANGE compare_config compare_config LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin` COMMENT \'The compare config as JSON-blob.\'');
        $this->addSql('ALTER TABLE external_judgement CHANGE jury_member jury_member VARCHAR(255) DEFAULT NULL COMMENT \'Name of user who verified the result / diference\'');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
