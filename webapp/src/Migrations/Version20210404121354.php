<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210404121354 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Use an auto increment integer column as primary key for judgehosts.';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        // First, add the new field on the judgehost table. For this to work, we first need to add a unique index on hostname, since otherwise foreign keys will break
        $this->addSql('CREATE UNIQUE INDEX hostname ON judgehost (hostname)');
        $this->addSql('ALTER TABLE judgehost DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE judgehost ADD judgehostid INT UNSIGNED AUTO_INCREMENT NOT NULL COMMENT \'Judgehost ID\' FIRST, ADD PRIMARY KEY (judgehostid)');

        // Now add the new judgehostid field on judgetasks and judgings, including indices
        $this->addSql('DROP INDEX hostname_valid_priority ON judgetask');
        $this->addSql('DROP INDEX hostname_jobid ON judgetask');
        $this->addSql('DROP INDEX specific_type ON judgetask');
        $this->addSql('ALTER TABLE judgetask ADD judgehostid INT UNSIGNED DEFAULT NULL COMMENT \'Judgehost ID\' AFTER judgetaskid');
        $this->addSql('CREATE INDEX judgehostid ON judgetask (judgehostid)');
        $this->addSql('ALTER TABLE judgetask ADD CONSTRAINT judgetask_ibfk_1 FOREIGN KEY (judgehostid) REFERENCES judgehost (judgehostid)');
        $this->addSql('CREATE INDEX judgehostid_jobid ON judgetask (judgehostid, jobid)');
        $this->addSql('CREATE INDEX judgehostid_valid_priority ON judgetask (judgehostid, valid, priority)');
        $this->addSql('CREATE INDEX specific_type ON judgetask (judgehostid, starttime, valid, type, priority, judgetaskid)');

        $this->addSql('ALTER TABLE judging DROP FOREIGN KEY judging_ibfk_3');
        $this->addSql('DROP INDEX judgehost ON judging');
        $this->addSql('ALTER TABLE judging ADD judgehostid INT UNSIGNED DEFAULT NULL COMMENT \'Judgehost ID\' AFTER endtime');
        $this->addSql('CREATE INDEX judgehostid ON judging (judgehostid)');
        $this->addSql('ALTER TABLE judging ADD CONSTRAINT judging_ibfk_3 FOREIGN KEY (judgehostid) REFERENCES judgehost (judgehostid)');

        // Now migrate the old data
        $this->addSql('UPDATE judging j INNER JOIN judgehost jh ON j.judgehost = jh.hostname SET j.judgehostid = jh.judgehostid');
        $this->addSql('UPDATE judgetask jt INNER JOIN judgehost jh ON jt.hostname = jh.hostname SET jt.judgehostid = jh.judgehostid');

        // Finally, remove the old columns
        $this->addSql('ALTER TABLE judgetask DROP hostname');
        $this->addSql('ALTER TABLE judging DROP judgehost');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        // First, re-add the old fields on the judging and judgetask tables
        $this->addSql('ALTER TABLE judgetask ADD hostname VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT \'hostname of the judge which executes the task\' AFTER judgehostid');
        $this->addSql('ALTER TABLE judging ADD judgehost VARCHAR(64) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT \'Resolvable hostname of judgehost\' AFTER judgehostid');

        // Now fix the data
        $this->addSql('UPDATE judging j INNER JOIN judgehost jh ON j.judgehostid = jh.judgehostid SET j.judgehost = jh.hostname');
        $this->addSql('UPDATE judgetask jt INNER JOIN judgehost jh ON jt.judgehostid = jh.judgehostid SET jt.hostname = jh.hostname');

        // Finally get rid of the old columns and indices. Also add the old indices
        $this->addSql('ALTER TABLE judgetask DROP FOREIGN KEY judgetask_ibfk_1');
        $this->addSql('DROP INDEX judgehostid ON judgetask');
        $this->addSql('DROP INDEX judgehostid_jobid ON judgetask');
        $this->addSql('DROP INDEX judgehostid_valid_priority ON judgetask');
        $this->addSql('DROP INDEX specific_type ON judgetask');
        $this->addSql('ALTER TABLE judgetask DROP judgehostid');
        $this->addSql('CREATE INDEX hostname_valid_priority ON judgetask (hostname(64), valid, priority)');
        $this->addSql('CREATE INDEX hostname_jobid ON judgetask (hostname(64), jobid)');
        $this->addSql('CREATE INDEX specific_type ON judgetask (hostname(64), starttime, valid, type, priority, judgetaskid)');

        $this->addSql('ALTER TABLE judging DROP FOREIGN KEY judging_ibfk_3');
        $this->addSql('DROP INDEX judgehostid ON judging');
        $this->addSql('ALTER TABLE judging DROP judgehostid');
        $this->addSql('ALTER TABLE judging ADD CONSTRAINT judging_ibfk_3 FOREIGN KEY (judgehost) REFERENCES judgehost (hostname)');
        $this->addSql('CREATE INDEX judgehost ON judging (judgehost)');

        $this->addSql('ALTER TABLE judgehost MODIFY judgehostid INT UNSIGNED NOT NULL COMMENT \'Judgehost ID\'');
        $this->addSql('ALTER TABLE judgehost DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE judgehost ADD PRIMARY KEY (hostname)');
        $this->addSql('DROP INDEX hostname ON judgehost');
        $this->addSql('ALTER TABLE judgehost DROP judgehostid');
    }
}
