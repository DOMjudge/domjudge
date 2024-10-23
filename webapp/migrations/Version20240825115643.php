<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240825115643 extends AbstractMigration
{
    // All remaining foreign keys are now cascade.
    // There is one exception: immutable_executable.immutable_execid is still referenced and removal is restricted.
    public function getDescription(): string
    {
        return 'Recreate \'restrict foreign key constraints\', cascading or setting to null where needed.';
    }

    public function up(Schema $schema): void
    {
        $this->dropKeys();
        $this->addKeys(true);
    }

    public function down(Schema $schema): void
    {
        $this->dropKeys();
        $this->addKeys(false);
    }

    public function dropKeys(): void
    {
        $this->addSql('ALTER TABLE debug_package DROP CONSTRAINT FK_9E17399BE0E4FC3E');
        $this->addSql('ALTER TABLE version DROP CONSTRAINT FK_BF1CD3C32271845');
        $this->addSql('ALTER TABLE version DROP CONSTRAINT FK_BF1CD3C3E0E4FC3E');
        $this->addSql('ALTER TABLE judging_run DROP CONSTRAINT FK_29A6E6E13CBA64F2');
        $this->addSql('ALTER TABLE judging_run DROP CONSTRAINT judging_run_ibfk_1');
        $this->addSql('ALTER TABLE judgetask DROP CONSTRAINT judgetask_ibfk_1');
        $this->addSql('ALTER TABLE submission DROP FOREIGN KEY FK_DB055AF3F132696E');
    }

    public function addKeys(bool $isUp): void
    {
        // foreign-keys that are related to judgehosts are set to null so that no data is lost.
        $cascadeClause = $isUp ? 'ON DELETE CASCADE' : '';
        $nullClause = $isUp ? 'ON DELETE SET NULL' : '';

        $this->addSql('ALTER TABLE version ADD CONSTRAINT `FK_BF1CD3C32271845` FOREIGN KEY (`langid`) REFERENCES `language` (`langid`) ' . $cascadeClause);
        $this->addSql('ALTER TABLE version ADD CONSTRAINT `FK_BF1CD3C3E0E4FC3E` FOREIGN KEY (`judgehostid`) REFERENCES `judgehost` (`judgehostid`) ' . $nullClause);
        $this->addSql('ALTER TABLE judging_run ADD CONSTRAINT `FK_29A6E6E13CBA64F2` FOREIGN KEY (`judgetaskid`) REFERENCES `judgetask` (`judgetaskid`) ' . $cascadeClause);
        $this->addSql('ALTER TABLE judging_run ADD CONSTRAINT `judging_run_ibfk_1` FOREIGN KEY (`testcaseid`) REFERENCES `testcase` (`testcaseid`) ' . $cascadeClause);
        $this->addSql('ALTER TABLE judgetask ADD CONSTRAINT `judgetask_ibfk_1` FOREIGN KEY (`judgehostid`) REFERENCES `judgehost` (`judgehostid`) ' . $nullClause);
        $this->addSql('ALTER TABLE debug_package ADD CONSTRAINT `FK_9E17399BE0E4FC3E` FOREIGN KEY (`judgehostid`) REFERENCES `judgehost` (`judgehostid`) ' . $nullClause);

        $clause = $isUp ? 'ON DELETE SET NULL' : 'ON DELETE CASCADE';
        $this->addSql('ALTER TABLE submission ADD CONSTRAINT FK_DB055AF3F132696E FOREIGN KEY (userid) REFERENCES user (userid) ' . $clause);
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
