<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240322141105 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add columns teamid, probid, cid and an uniq index with them to the balloon table.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs

        // remove duplicates
        $this->addSql('DELETE b FROM balloon as b LEFT JOIN (SELECT min(b.balloonid) AS min_balloonid FROM balloon as b LEFT JOIN submission as s USING (submitid) GROUP BY teamid, probid, cid) as c ON(b.balloonid = c.min_balloonid) WHERE c.min_balloonid IS NULL');

        $this->addSql('ALTER TABLE balloon ADD teamid INT UNSIGNED DEFAULT NULL COMMENT \'Team ID\', ADD probid INT UNSIGNED DEFAULT NULL COMMENT \'Problem ID\', ADD cid INT UNSIGNED DEFAULT NULL COMMENT \'Contest ID\'');
        $this->addSql('ALTER TABLE balloon ADD CONSTRAINT FK_643B3B904DD6ABF3 FOREIGN KEY (teamid) REFERENCES team (teamid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE balloon ADD CONSTRAINT FK_643B3B90EF049279 FOREIGN KEY (probid) REFERENCES problem (probid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE balloon ADD CONSTRAINT FK_643B3B904B30D9C4 FOREIGN KEY (cid) REFERENCES contest (cid) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_643B3B904DD6ABF3 ON balloon (teamid)');
        $this->addSql('CREATE INDEX IDX_643B3B90EF049279 ON balloon (probid)');
        $this->addSql('CREATE INDEX IDX_643B3B904B30D9C4 ON balloon (cid)');
        $this->addSql('CREATE UNIQUE INDEX unique_problem ON balloon (cid, teamid, probid)');

        // copy data
        $this->addSql('UPDATE balloon AS b JOIN submission AS s USING (submitid) SET b.teamid = s.teamid, b.probid = s.probid, b.cid = s.cid');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE balloon DROP FOREIGN KEY FK_643B3B904DD6ABF3');
        $this->addSql('ALTER TABLE balloon DROP FOREIGN KEY FK_643B3B90EF049279');
        $this->addSql('ALTER TABLE balloon DROP FOREIGN KEY FK_643B3B904B30D9C4');
        $this->addSql('DROP INDEX IDX_643B3B904DD6ABF3 ON balloon');
        $this->addSql('DROP INDEX IDX_643B3B90EF049279 ON balloon');
        $this->addSql('DROP INDEX IDX_643B3B904B30D9C4 ON balloon');
        $this->addSql('DROP INDEX unique_problem ON balloon');
        $this->addSql('ALTER TABLE balloon DROP teamid, DROP probid, DROP cid');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
