<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200522133213 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Add functionality to repeat rejudgings N times.';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('ALTER TABLE rejudging ADD `repeat` INT UNSIGNED DEFAULT NULL COMMENT \'Number of times this rejudging will be repeated.\', ADD repeat_rejudgingid INT UNSIGNED DEFAULT NULL COMMENT \'In case repeat is set, this will mark the first rejudgingid.\'');
        $this->addSql('ALTER TABLE rejudging ADD CONSTRAINT FK_719382B18D95A49F FOREIGN KEY (repeat_rejudgingid) REFERENCES rejudging (rejudgingid) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_719382B18D95A49F ON rejudging (repeat_rejudgingid)');
    }

    public function down(Schema $schema) : void
    {
        $this->addSql('ALTER TABLE rejudging DROP FOREIGN KEY FK_719382B18D95A49F');
        $this->addSql('DROP INDEX IDX_719382B18D95A49F ON rejudging');
        $this->addSql('ALTER TABLE rejudging DROP `repeat`, DROP repeat_rejudgingid');
    }
}
