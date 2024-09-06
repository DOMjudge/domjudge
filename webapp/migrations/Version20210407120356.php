<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use ZipArchive;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210407120356 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription() : string
    {
        return 'Populate immutable executable tables.';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        if ($schema->getTable('executable')->hasColumn('zipfile')) {
            $oldRows = $this->connection->executeQuery('SELECT execid, zipfile FROM executable')->fetchAllAssociative();
            foreach ($oldRows as $oldRow) {
                $this->connection->executeStatement('INSERT INTO immutable_executable (`userid`) VALUES (null)');
                $immutable_execid = $this->connection->lastInsertId();

                $tmpzip = tempnam('/tmp', 'zipfile');
                file_put_contents($tmpzip, $oldRow['zipfile']);
                $zip = new ZipArchive();
                $zip->open($tmpzip, ZIPARCHIVE::CHECKCONS);

                for ($idx = 0; $idx < $zip->numFiles; $idx++) {
                    $filename = basename($zip->getNameIndex($idx));
                    $content = $zip->getFromIndex($idx);

                    // In doubt make files executable, but try to read it from the zip file.
                    $executableBit = '1';
                    if ($zip->getExternalAttributesIndex($idx, $opsys, $attr)
                        && $opsys == ZipArchive::OPSYS_UNIX
                        && (($attr >> 16) & 0100) === 0) {
                        $executableBit = '0';
                    }
                    $this->connection->executeStatement(
                        'INSERT INTO executable_file (`immutable_execid`, `filename`, `ranknumber`, `file_content`, `hash`, `is_executable`)'
                        . ' VALUES (?, ?, ?, ?, ?, ?)',
                        [$immutable_execid, $filename, $idx, $content, md5($content), $executableBit]
                    );
                }

                $this->connection->executeStatement(
                    'UPDATE executable SET immutable_execid = :immutable_execid WHERE execid = :execid',
                    ['immutable_execid' => $immutable_execid, 'execid' => $oldRow['execid']]
                );
            }

            $this->connection->executeStatement('ALTER TABLE `executable` DROP COLUMN `zipfile`');
        }
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            true,
            'Downgrading is not supported'
        );
    }
}
