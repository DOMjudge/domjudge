<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use ZipArchive;

final class Version20201219154652 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function getDescription(): string
    {
        return 'Load executables in new format.';
    }


    public function up(Schema $schema): void
    {
        $this->skipIf(
            !$schema->getTable('executable')->hasColumn('zipfile'),
            'column zipfile doesnt exist anymore'
        );

        // TODO: add all kinds of error checking here
        $oldRows = $this->connection->executeQuery('SELECT execid, zipfile FROM executable')->fetchAll();
        foreach ($oldRows as $oldRow) {
            $this->connection->exec('INSERT INTO immutable_executable (`userid`) VALUES (null)');
            $immutable_execid = $this->connection->lastInsertId();

            $tmpzip = tempnam('/tmp', 'zipfile');
            file_put_contents($tmpzip, $oldRow['zipfile']);
            $zip = new ZipArchive();
            $zip->open($tmpzip, ZIPARCHIVE::CHECKCONS);

            for ($idx = 0; $idx < $zip->numFiles; $idx++) {
                $filename = basename($zip->getNameIndex($idx));
                $content = $zip->getFromIndex($idx);

                $this->connection->exec(
                    'INSERT INTO executable_file '
                    . '(`immutable_execid`, `filename`, `ranknumber`, `file_content`, `is_executable`) '
                    . 'VALUES (' . $immutable_execid . ', "' . $filename . '", '
                    . $idx . ', 0x' . strtoupper(bin2hex($content)) . ', '
                    // TODO: how to retrieve executable bit from zip?
                    . '1)'
                );
            }

            $this->connection->exec(
                'UPDATE executable SET immutable_execid = '
                . $immutable_execid . ' WHERE execid = "' . $oldRow['execid'] . '"'
            );
        }

        $this->addSql('ALTER TABLE `executable` DROP COLUMN `zipfile`');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            true,
            'Downgrading is not supported'
        );
    }
}
