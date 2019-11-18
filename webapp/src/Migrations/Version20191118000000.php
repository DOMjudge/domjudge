<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20191118000000 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'bump default limits in configuration';
    }

    public function up(Schema $schema) : void
    {
        $this->skipIf(1 != getenv('DB_FIRST_INSTALL'), 'Only bumping default limits on clean installation.');
        $this->addSql(<<<SQL
UPDATE `configuration` SET `value` = '0' WHERE `name` = 'compile_penalty';
UPDATE `configuration` SET `value` = '{"memory-limit":99,"output-limit":99,"run-error":99,"timelimit":99,"wrong-answer":99,"no-output":99,"correct":1}' WHERE `name` = 'results_prio';

UPDATE `configuration` SET `value` = '2097152' WHERE `name` = 'memory_limit';
UPDATE `configuration` SET `value` = '8192' WHERE `name` = 'output_limit';

UPDATE `configuration` SET `value` = '5' WHERE `name` = 'update_judging_seconds';

UPDATE `configuration` SET `value` = '1' WHERE `name` = 'show_pending';
UPDATE `configuration` SET `value` = '200' WHERE `name` = 'thumbnail_size';
UPDATE `configuration` SET `value` = '1' WHERE `name` = 'show_limits_on_team_page';
SQL
        );
    }

    public function down(Schema $schema) : void
    {
        $this->warnIf(
            true,
            'Downgrading configuration limits will be skipped.'
        );
    }
}
