<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20190803145008 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'move testcase and judging run fields with lots of data to separate tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
CREATE TABLE `testcase_content` (
    `testcaseid` int(4) UNSIGNED NOT NULL COMMENT 'Testcase ID',
    `input` longblob DEFAULT NULL COMMENT 'Input data(DC2Type:blobtext)',
    `output` longblob DEFAULT NULL COMMENT 'Output data(DC2Type:blobtext)',
    `image` longblob DEFAULT NULL COMMENT 'A graphical representation of the testcase(DC2Type:blobtext)',
    `image_thumb` longblob DEFAULT NULL COMMENT 'Automatically created thumbnail of the image(DC2Type:blobtext)',
    PRIMARY KEY  (`testcaseid`),
    CONSTRAINT `testcase_content_ibfk_1` FOREIGN KEY (`testcaseid`) REFERENCES `testcase` (`testcaseid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores contents of testcase'
SQL
        );

        $this->addSql(<<<SQL
CREATE TABLE `judging_run_output` (
    `runid` int(4) unsigned NOT NULL COMMENT 'Run ID',
    `output_run` longblob DEFAULT NULL COMMENT 'Output of running the program(DC2Type:blobtext)',
    `output_diff` longblob DEFAULT NULL COMMENT 'Diffing the program output and testcase output(DC2Type:blobtext)',
    `output_error` longblob DEFAULT NULL COMMENT 'Standard error output of the program(DC2Type:blobtext)',
    `output_system` longblob DEFAULT NULL COMMENT 'Judging system output(DC2Type:blobtext)',
    PRIMARY KEY  (`runid`),
    KEY `runid` (`runid`),
    CONSTRAINT `judging_run_output_ibfk_1` FOREIGN KEY (`runid`) REFERENCES `judging_run` (`runid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores output of judging run'
SQL
        );

        $this->addSql("INSERT INTO testcase_content (testcaseid, input, output, image, image_thumb) SELECT testcaseid, input, output, image, image_thumb FROM testcase");
        $this->addSql("INSERT INTO judging_run_output (runid, output_run, output_diff, output_error, output_system) SELECT runid, output_run, output_diff, output_error, output_system FROM judging_run");

        $this->addSql(<<<SQL
ALTER TABLE `testcase`
    DROP COLUMN `input`,
    DROP COLUMN `output`,
    DROP COLUMN `image`,
    DROP COLUMN `image_thumb`
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `judging_run`
    DROP COLUMN `output_run`,
    DROP COLUMN `output_diff`,
    DROP COLUMN `output_error`,
    DROP COLUMN `output_system`
SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<SQL
alter table testcase
    ADD COLUMN input longblob COMMENT 'Input data' AFTER testcaseid,
    ADD COLUMN output longblob COMMENT 'Output data' AFTER input,
    ADD COLUMN image longblob COMMENT 'A graphical representation of this testcase' AFTER description,
    ADD COLUMN image_thumb longblob COMMENT 'Aumatically created thumbnail of the image' AFTER image
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE judging_run
    ADD COLUMN output_run blob COMMENT 'Output of running the program' AFTER runtime,
    ADD COLUMN output_diff blob COMMENT 'Diffing the program output and testcase output' AFTER output_run,
    ADD COLUMN output_error blob COMMENT 'Standard error output of the program' AFTER output_diff,
    ADD COLUMN output_system longblob COMMENT 'Judging system output' AFTER output_error
SQL
        );

        $this->addSql(<<<SQL
UPDATE testcase INNER JOIN testcase_content USING (testcaseid) SET
    testcase.input = testcase_content.input,
    testcase.output = testcase_content.output,
    testcase.image = testcase_content.image,
    testcase.image_thumb = testcase_content.image_thumb
SQL
        );
        $this->addSql(<<<SQL
UPDATE judging_run INNER JOIN judging_run_output using (runid) SET
    judging_run.output_run = judging_run_output.output_run,
    judging_run.output_diff = judging_run_output.output_diff,
    judging_run.output_error = judging_run_output.output_error,
    judging_run.output_system = judging_run_output.output_system
SQL
        );

        $this->addSql("DROP TABLE testcase_content, judging_run_output");
    }
}
