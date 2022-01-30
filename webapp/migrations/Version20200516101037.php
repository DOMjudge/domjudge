<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200516101037 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add verification fields to external judgements';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql',
            "Migration can only be executed safely on 'mysql'.");

        $this->addSql("ALTER TABLE external_judgement
            ADD verified TINYINT(1) DEFAULT '0' NOT NULL COMMENT 'Result / difference verified?' AFTER result,
            ADD jury_member VARCHAR(255) DEFAULT NULL COMMENT 'Name of user who verified the result / diference' AFTER verified,
            ADD verify_comment VARCHAR(255) DEFAULT NULL COMMENT 'Optional additional information provided by the verifier' AFTER jury_member");
        $this->addSql('CREATE INDEX verified ON external_judgement (verified)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql',
            "Migration can only be executed safely on 'mysql'.");

        $this->addSql('DROP INDEX verified ON external_judgement');
        $this->addSql('ALTER TABLE external_judgement DROP verified, DROP jury_member, DROP verify_comment');
    }
}
