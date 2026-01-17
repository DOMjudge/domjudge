<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250704181912 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add basic support for partial scoring';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE testcase_group (testcase_group_id INT UNSIGNED AUTO_INCREMENT NOT NULL COMMENT \'Testcase group ID\', name VARCHAR(255) NOT NULL COMMENT \'Name of the testcase group\', accept_score NUMERIC(32, 9) DEFAULT NULL COMMENT \'Score if this group is accepted\', range_lower_bound NUMERIC(32, 9) DEFAULT NULL COMMENT \'Lower bound of the score range\', range_upper_bound NUMERIC(32, 9) DEFAULT NULL COMMENT \'Upper bound of the score range\', aggregation_type VARCHAR(255) DEFAULT \'sum\' NOT NULL COMMENT \'How to aggregate scores for this group\', ignore_sample TINYINT(1) DEFAULT 0 NOT NULL COMMENT \'Ignore the sample testcases when aggregating scores\', PRIMARY KEY(testcase_group_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'Testcase group metadata\' ');
        $this->addSql('ALTER TABLE testcase ADD testcase_group_id INT UNSIGNED DEFAULT NULL COMMENT \'Testcase group ID\'');
        $this->addSql('ALTER TABLE testcase ADD CONSTRAINT FK_4C1E5C391FF421A3 FOREIGN KEY (testcase_group_id) REFERENCES testcase_group (testcase_group_id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_4C1E5C391FF421A3 ON testcase (testcase_group_id)');
        $this->addSql('ALTER TABLE judging_run ADD score NUMERIC(32, 9) DEFAULT \'0.000000000\' NOT NULL COMMENT \'Optional score for this run, e.g. for partial scoring\'');
        $this->addSql('ALTER TABLE judging ADD score NUMERIC(32, 9) DEFAULT \'0.000000000\' NOT NULL COMMENT \'Optional score for this run, e.g. for partial scoring\'');
        $this->addSql('ALTER TABLE testcase_group ADD parent_id INT UNSIGNED DEFAULT NULL COMMENT \'Testcase group ID\'');
        $this->addSql('ALTER TABLE testcase_group ADD CONSTRAINT FK_F02888FE727ACA70 FOREIGN KEY (parent_id) REFERENCES testcase_group (testcase_group_id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_F02888FE727ACA70 ON testcase_group (parent_id)');
        $this->addSql('ALTER TABLE problem ADD parent_testcase_group_id INT UNSIGNED DEFAULT NULL COMMENT \'Testcase group ID\'');
        $this->addSql('ALTER TABLE problem ADD CONSTRAINT FK_D7E7CCC8A090DCC7 FOREIGN KEY (parent_testcase_group_id) REFERENCES testcase_group (testcase_group_id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_D7E7CCC8A090DCC7 ON problem (parent_testcase_group_id)');
        $this->addSql('ALTER TABLE testcase_group ADD output_validator_flags VARCHAR(255) DEFAULT NULL COMMENT \'Flags for output validation\'');
        $this->addSql('ALTER TABLE testcase_group ADD on_reject_continue TINYINT(1) DEFAULT 0 NOT NULL COMMENT \'Continue on reject\'');
        $this->addSql('ALTER TABLE scorecache ADD score_public NUMERIC(32, 9) DEFAULT \'0.000000000\' NOT NULL COMMENT \'Optional score for this run, e.g. for partial scoring\', ADD score_restricted NUMERIC(32, 9) DEFAULT \'0.000000000\' NOT NULL COMMENT \'Optional score for this run, e.g. for partial scoring (for restricted audience)\'');
        $this->addSql('ALTER TABLE rankcache ADD score_public NUMERIC(32, 9) DEFAULT \'0.000000000\' NOT NULL COMMENT \'Optional score for this run, e.g. for partial scoring\', ADD score_restricted NUMERIC(32, 9) DEFAULT \'0.000000000\' NOT NULL COMMENT \'Optional score for this run, e.g. for partial scoring (for restricted audience)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rankcache DROP score_public, DROP score_restricted');
        $this->addSql('ALTER TABLE scorecache DROP score_public, DROP score_restricted');
        $this->addSql('ALTER TABLE testcase_group DROP on_reject_continue');
        $this->addSql('ALTER TABLE testcase_group DROP output_validator_flags');
        $this->addSql('ALTER TABLE problem DROP FOREIGN KEY FK_D7E7CCC8A090DCC7');
        $this->addSql('DROP INDEX IDX_D7E7CCC8A090DCC7 ON problem');
        $this->addSql('ALTER TABLE problem DROP parent_testcase_group_id');
        $this->addSql('ALTER TABLE testcase_group DROP FOREIGN KEY FK_F02888FE727ACA70');
        $this->addSql('DROP INDEX IDX_F02888FE727ACA70 ON testcase_group');
        $this->addSql('ALTER TABLE testcase_group DROP parent_id');
        $this->addSql('ALTER TABLE judging DROP score');
        $this->addSql('ALTER TABLE judging_run DROP score');
        $this->addSql('ALTER TABLE testcase DROP FOREIGN KEY FK_4C1E5C391FF421A3');
        $this->addSql('DROP INDEX IDX_4C1E5C391FF421A3 ON testcase');
        $this->addSql('ALTER TABLE testcase DROP testcase_group_id');
        $this->addSql('DROP TABLE testcase_group');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
