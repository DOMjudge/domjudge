<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250620090108 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE team_category_team (categoryid INT UNSIGNED NOT NULL COMMENT \'Team category ID\', teamid INT UNSIGNED NOT NULL COMMENT \'Team ID\', INDEX IDX_3A19F9C99B32FD3 (categoryid), INDEX IDX_3A19F9C94DD6ABF3 (teamid), PRIMARY KEY(categoryid, teamid)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE team_category_team ADD CONSTRAINT FK_3A19F9C99B32FD3 FOREIGN KEY (categoryid) REFERENCES team_category (categoryid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE team_category_team ADD CONSTRAINT FK_3A19F9C94DD6ABF3 FOREIGN KEY (teamid) REFERENCES team (teamid) ON DELETE CASCADE');
        $this->addSql('INSERT INTO team_category_team (categoryid, teamid) SELECT categoryid, teamid FROM team');
        $this->addSql('ALTER TABLE team DROP FOREIGN KEY team_ibfk_1');
        $this->addSql('DROP INDEX categoryid ON team');
        $this->addSql('ALTER TABLE team DROP categoryid');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE team ADD categoryid INT UNSIGNED DEFAULT NULL COMMENT \'Team category ID\'');
        $this->addSql('ALTER TABLE team ADD CONSTRAINT team_ibfk_1 FOREIGN KEY (categoryid) REFERENCES team_category (categoryid) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX categoryid ON team (categoryid)');
        $this->addSql('UPDATE team SET categoryid = (SELECT MIN(categoryid) from team_category_team WHERE team_category_team.teamid = team.teamid)');
        $this->addSql('ALTER TABLE team_category_team DROP FOREIGN KEY FK_3A19F9C99B32FD3');
        $this->addSql('ALTER TABLE team_category_team DROP FOREIGN KEY FK_3A19F9C94DD6ABF3');
        $this->addSql('DROP TABLE team_category_team');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
