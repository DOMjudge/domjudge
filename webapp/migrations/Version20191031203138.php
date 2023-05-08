<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Language;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191031203138 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription(): string
    {
        return 'add .py to Python 3 extension list and remove it for Python 2';
    }

    public function up(Schema $schema): void
    {
        $python2 = $this->connection->fetchAssociative('SELECT * FROM language WHERE langid = :py2', ['py2' => 'py2']);
        $python3 = $this->connection->fetchAssociative('SELECT * FROM language WHERE langid = :py3', ['py3' => 'py3']);
        if ($python2 === false ||
            $python3 === false ||
            $python2['allow_submit'] ||
            $python3['allow_submit'] ||
            json_decode($python2['extensions'], true) !== ['py2', 'py'] ||
            json_decode($python3['extensions'], true) !== ['py3']) {
            return;
        }

        $this->addSql('UPDATE language SET extensions = \'["py3","py"]\' WHERE langid = \'py3\'');
        $this->addSql('UPDATE language SET extensions = \'["py2"]\' WHERE langid = \'py2\'');
    }

    public function down(Schema $schema): void
    {
        $python2 = $this->connection->fetchAssociative('SELECT * FROM language WHERE langid = :py2', ['py2' => 'py2']);
        $python3 = $this->connection->fetchAssociative('SELECT * FROM language WHERE langid = :py3', ['py3' => 'py3']);

        if ($python2 === false ||
            $python3 === false ||
            $python2['allow_submit'] ||
            $python3['allow_submit'] ||
            json_decode($python2['extensions'], true) !== ['py2'] ||
            json_decode($python3['extensions'], true) !== ['py3', 'py']) {
            return;
        }

        $this->addSql('UPDATE language SET extensions = \'["py2","py"]\' WHERE langid = \'py2\'');
        $this->addSql('UPDATE language SET extensions = \'["py"]\' WHERE langid = \'py3\'');
    }
}
