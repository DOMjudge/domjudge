<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251024122021 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change language ID to be an auto increment integer';
    }

    public function up(Schema $schema): void
    {
        // We need to do some juggling to get this to work:

        // - First we drop the foreign keys from tables referencing langid. We also drop compound
        $this->addSql('ALTER TABLE submission DROP FOREIGN KEY submission_ibfk_4');
        $this->addSql('ALTER TABLE problemlanguage DROP FOREIGN KEY FK_46B150BB2271845');
        $this->addSql('ALTER TABLE version DROP FOREIGN KEY FK_BF1CD3C32271845');
        $this->addSql('ALTER TABLE contestlanguage DROP FOREIGN KEY FK_ADCB43232271845');

        // - Then we add a temporary integer langid column to all these tables, since we can't
        //   change the langid column itself, because MySQL will try to convert the strings to
        //   integers and fail. We set the langid column in the language table to auto increment,
        //   so it's filled by MySQL.
        $this->addSql('ALTER TABLE language ADD langid_int INT UNSIGNED AUTO_INCREMENT UNIQUE AFTER langid');
        $this->addSql('ALTER TABLE contestlanguage ADD langid_int INT UNSIGNED NOT NULL AFTER langid');
        $this->addSql('ALTER TABLE problemlanguage ADD langid_int INT UNSIGNED NOT NULL AFTER langid');
        $this->addSql('ALTER TABLE submission ADD langid_int INT UNSIGNED DEFAULT NULL AFTER langid');
        $this->addSql('ALTER TABLE version ADD langid_int INT UNSIGNED DEFAULT NULL AFTER langid');

        // - Now we copy the langid_int values to other tables.
        $this->addSql('UPDATE contestlanguage c JOIN language l ON c.langid = l.langid SET c.langid_int = l.langid_int');
        $this->addSql('UPDATE problemlanguage p JOIN language l ON p.langid = l.langid SET p.langid_int = l.langid_int');
        $this->addSql('UPDATE submission s JOIN language l ON s.langid = l.langid SET s.langid_int = l.langid_int');
        $this->addSql('UPDATE version v JOIN language l ON v.langid = l.langid SET v.langid_int = l.langid_int');
        $this->addSql('UPDATE auditlog a JOIN language l ON a.dataid = l.langid AND a.datatype = "language" SET a.dataid = l.langid_int WHERE a.dataid IS NOT NULL');

        // - Then we drop the old langid columns and drop any (compound) primary keys that use it.
        $this->addSql('ALTER TABLE language DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE language DROP COLUMN langid');
        $this->addSql('ALTER TABLE contestlanguage DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE contestlanguage DROP COLUMN langid');
        $this->addSql('ALTER TABLE problemlanguage DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE problemlanguage DROP COLUMN langid');
        $this->addSql('ALTER TABLE submission DROP COLUMN langid');
        $this->addSql('ALTER TABLE version DROP COLUMN langid');

        // - Next, we rename the langid_int columns back to langid.
        $this->addSql('ALTER TABLE language CHANGE langid_int langid INT UNSIGNED AUTO_INCREMENT NOT NULL COMMENT \'Language ID\'');
        $this->addSql('ALTER TABLE contestlanguage CHANGE langid_int langid INT UNSIGNED NOT NULL COMMENT \'Language ID\'');
        $this->addSql('ALTER TABLE problemlanguage CHANGE langid_int langid INT UNSIGNED NOT NULL COMMENT \'Language ID\'');
        $this->addSql('ALTER TABLE submission CHANGE langid_int langid INT UNSIGNED DEFAULT NULL COMMENT \'Language ID\'');
        $this->addSql('ALTER TABLE version CHANGE langid_int langid INT UNSIGNED DEFAULT NULL COMMENT \'Language ID\'');

        // - Finally we add back all primary and foreign keys.
        $this->addSql('ALTER TABLE language ADD PRIMARY KEY (langid), DROP INDEX langid_int');
        $this->addSql('ALTER TABLE contestlanguage ADD PRIMARY KEY (cid, langid)');
        $this->addSql('ALTER TABLE problemlanguage ADD PRIMARY KEY (probid, langid)');

        $this->addSql('ALTER TABLE submission ADD INDEX langid (langid), ADD CONSTRAINT submission_ibfk_4 FOREIGN KEY (langid) REFERENCES language (langid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE problemlanguage ADD INDEX IDX_46B150BB2271845 (langid), ADD CONSTRAINT FK_46B150BB2271845 FOREIGN KEY (langid) REFERENCES language (langid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE version ADD INDEX IDX_BF1CD3C32271845 (langid), ADD CONSTRAINT FK_BF1CD3C32271845 FOREIGN KEY (langid) REFERENCES language (langid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contestlanguage ADD INDEX IDX_ADCB43232271845 (langid), ADD CONSTRAINT FK_ADCB43232271845 FOREIGN KEY (langid) REFERENCES language (langid) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // When migrating back, we do the logic in reverse but we have custom logic for setting
        // the string langid. We set it to the external ID except for a set of 10 known languages
        // where the external ID differs from our previously used langid.

        $this->addSql('ALTER TABLE submission DROP FOREIGN KEY submission_ibfk_4');
        $this->addSql('ALTER TABLE problemlanguage DROP FOREIGN KEY FK_46B150BB2271845');
        $this->addSql('ALTER TABLE version DROP FOREIGN KEY FK_BF1CD3C32271845');
        $this->addSql('ALTER TABLE contestlanguage DROP FOREIGN KEY FK_ADCB43232271845');

        $this->addSql('ALTER TABLE language ADD langid_str VARCHAR(32) NOT NULL AFTER langid');
        $this->addSql('ALTER TABLE contestlanguage ADD langid_str VARCHAR(32) NOT NULL AFTER langid');
        $this->addSql('ALTER TABLE problemlanguage ADD langid_str VARCHAR(32) NOT NULL AFTER langid');
        $this->addSql('ALTER TABLE submission ADD langid_str VARCHAR(32) DEFAULT NULL AFTER langid');
        $this->addSql('ALTER TABLE version ADD langid_str VARCHAR(32) DEFAULT NULL AFTER langid');

        $this->addSql("UPDATE language SET langid_str = CASE externalid
            WHEN 'ada' THEN 'adb'
            WHEN 'haskell' THEN 'hs'
            WHEN 'javascript' THEN 'js'
            WHEN 'kotlin' THEN 'kt'
            WHEN 'pascal' THEN 'pas'
            WHEN 'prolog' THEN 'plg'
            WHEN 'python3' THEN 'py3'
            WHEN 'python2' THEN 'py2'
            WHEN 'ruby' THEN 'rb'
            WHEN 'rust' THEN 'rs'
            ELSE externalid
        END");

        $this->addSql('UPDATE contestlanguage c JOIN language l ON c.langid = l.langid SET c.langid_str = l.langid_str');
        $this->addSql('UPDATE problemlanguage p JOIN language l ON p.langid = l.langid SET p.langid_str = l.langid_str');
        $this->addSql('UPDATE submission s JOIN language l ON s.langid = l.langid SET s.langid_str = l.langid_str');
        $this->addSql('UPDATE version v JOIN language l ON v.langid = l.langid SET v.langid_str = l.langid_str');
        $this->addSql('UPDATE auditlog a JOIN language l ON a.dataid = l.langid AND a.datatype = "language" SET a.dataid = l.langid_str WHERE a.dataid IS NOT NULL');

        $this->addSql('ALTER TABLE language DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE language DROP COLUMN langid');
        $this->addSql('ALTER TABLE contestlanguage DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE contestlanguage DROP COLUMN langid');
        $this->addSql('ALTER TABLE problemlanguage DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE problemlanguage DROP COLUMN langid');
        $this->addSql('ALTER TABLE submission DROP COLUMN langid');
        $this->addSql('ALTER TABLE version DROP COLUMN langid');

        $this->addSql('ALTER TABLE language CHANGE langid_str langid VARCHAR(32) NOT NULL COMMENT \'Language ID\'');
        $this->addSql('ALTER TABLE contestlanguage CHANGE langid_str langid VARCHAR(32) NOT NULL COMMENT \'Language ID\'');
        $this->addSql('ALTER TABLE problemlanguage CHANGE langid_str langid VARCHAR(32) NOT NULL COMMENT \'Language ID\'');
        $this->addSql('ALTER TABLE submission CHANGE langid_str langid VARCHAR(32) DEFAULT NULL COMMENT \'Language ID\'');
        $this->addSql('ALTER TABLE version CHANGE langid_str langid VARCHAR(32) DEFAULT NULL COMMENT \'Language ID\'');

        $this->addSql('ALTER TABLE language ADD PRIMARY KEY (langid)');
        $this->addSql('ALTER TABLE contestlanguage ADD PRIMARY KEY (cid, langid)');
        $this->addSql('ALTER TABLE problemlanguage ADD PRIMARY KEY (probid, langid)');

        $this->addSql('ALTER TABLE submission ADD INDEX langid (langid), ADD CONSTRAINT submission_ibfk_4 FOREIGN KEY (langid) REFERENCES language (langid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE problemlanguage ADD INDEX IDX_46B150BB2271845 (langid), ADD CONSTRAINT FK_46B150BB2271845 FOREIGN KEY (langid) REFERENCES language (langid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE version ADD INDEX IDX_BF1CD3C32271845 (langid), ADD CONSTRAINT FK_BF1CD3C32271845 FOREIGN KEY (langid) REFERENCES language (langid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contestlanguage ADD INDEX IDX_ADCB43232271845 (langid), ADD CONSTRAINT FK_ADCB43232271845 FOREIGN KEY (langid) REFERENCES language (langid) ON DELETE CASCADE');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
