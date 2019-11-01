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

    public function getDescription(): string
    {
        return 'add .py to Python 3 extension list and remove it for Python 2';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $em = $this->container->get('doctrine')->getManager();
        /** @var Language $python2 */
        $python2 = $em->getRepository(Language::class)->find('py2');
        /** @var Language $python3 */
        $python3 = $em->getRepository(Language::class)->find('py3');
        $this->skipIf($python2 === null || $python3 === null, 'Python 2 or 3 language not found');
        $this->skipIf($python2->getAllowSubmit(), 'Python 2 language enabled');
        $this->skipIf($python3->getAllowSubmit(), 'Python 3 language enabled');
        $this->skipIf($python2->getExtensions() !== ['py2', 'py'], 'Python 2 extensions modified');
        $this->skipIf($python3->getExtensions() !== ['py3'], 'Python 3 extensions modified');

        $this->addSql('UPDATE language SET extensions = \'["py3","py"]\' WHERE langid = \'py3\'');
        $this->addSql('UPDATE language SET extensions = \'["py2"]\' WHERE langid = \'py2\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $em = $this->container->get('doctrine')->getManager();
        /** @var Language $python2 */
        $python2 = $em->getRepository(Language::class)->find('py2');
        /** @var Language $python3 */
        $python3 = $em->getRepository(Language::class)->find('py3');
        $this->skipIf($python2 === null || $python3 === null, 'Python 2 or 3 language not found');
        $this->skipIf($python2->getAllowSubmit(), 'Python 2 language enabled');
        $this->skipIf($python3->getAllowSubmit(), 'Python 3 language enabled');
        $this->skipIf($python2->getExtensions() !== ['py2'], 'Python 2 extensions modified');
        $this->skipIf($python3->getExtensions() !== ['py3', 'py'], 'Python 3 extensions modified');

        $this->addSql('UPDATE language SET extensions = \'["py2","py"]\' WHERE langid = \'py2\'');
        $this->addSql('UPDATE language SET extensions = \'["py"]\' WHERE langid = \'py3\'');
    }
}
