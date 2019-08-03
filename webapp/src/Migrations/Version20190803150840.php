<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20190803150840 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add external_ccs_submission_url configuration option';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
INSERT INTO `configuration` (`name`, `value`, `type`, `public`, `category`, `description`) VALUES
    ('external_ccs_submission_url', '""', 'string', '0', 'Misc', 'URL of a submission detail page on the external CCS. Placeholder :id: will be replaced by submission ID. Leave empty to not display links to external CCS')
SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM configuration WHERE name = 'external_ccs_submission_url'");
    }
}
