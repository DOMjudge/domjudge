<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241015085654 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `judgetask`
                       MODIFY COLUMN `type` ENUM(\'judging_run\', \'generic_task\', \'config_check\', \'debug_info\', \'prefetch\', \'output_visualization\') DEFAULT \'judging_run\' NOT NULL COMMENT \'Type of the judge task.(DC2Type:judge_task_type)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `judgetask`
                       MODIFY COLUMN `type` ENUM(\'judging_run\', \'generic_task\', \'config_check\', \'debug_info\', \'prefetch\') DEFAULT \'judging_run\' NOT NULL COMMENT \'Type of the judge task.(DC2Type:judge_task_type)\'');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
