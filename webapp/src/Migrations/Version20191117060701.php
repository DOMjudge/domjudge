<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191117060701 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'change description of external_ccs_submission_url configuration option';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql(<<<SQL
UPDATE `configuration` SET
  `description` = 'URL of a submission detail page on the external CCS. Placeholder [id] will be replaced by submission ID and [contest] by the contest ID. Leave empty to not display links to external CCS',
  `value` = REPLACE(`value`, ':id:', '[id]')
WHERE `name` = 'external_ccs_submission_url'
SQL
        );
    }

    public function down(Schema $schema) : void
    {
        $this->addSql(<<<SQL
UPDATE `configuration` SET
  `description` = 'URL of a submission detail page on the external CCS. Placeholder :id: will be replaced by submission ID. Leave empty to not display links to external CCS',
  `value` = REPLACE(`value`, '[id]', ':id:')
WHERE `name` = 'external_ccs_submission_url'
SQL
        );
    }
}
