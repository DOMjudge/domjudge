<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Executable;
use App\Entity\Problem;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

/**
 * Class ProblemFixture
 * @package App\DataFixtures
 */
class ProblemFixture extends AbstractExampleDataFixture implements DependentFixtureInterface
{
    public const HELLO_REFERENCE    = 'hello';
    public const FLTCMP_REFERENCE   = 'fltcmp';
    public const BOOLFIND_REFERENCE = 'boolfind';

    /**
     * @var string
     */
    protected $projectDir;

    /**
     * ProblemFixture constructor.
     * @param string $projectDir
     */
    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    /**
     * Load data fixtures with the passed EntityManager
     *
     * @param ObjectManager $manager
     */
    public function load(ObjectManager $manager)
    {
        $examplesDir = sprintf('%s/public/doc/examples/', $this->projectDir);

        $hello = new Problem();
        $hello
            ->setExternalid('hello')
            ->setName('Hello World')
            ->setTimelimit(5);
        if (file_exists($examplesDir . 'hello.pdf')) {
            $hello
                ->setProblemtextType('pdf')
                ->setProblemtext(file_get_contents($examplesDir . 'hello.pdf'));
        }

        $fltcmp = new Problem();
        $fltcmp
            ->setExternalid('fltcmp')
            ->setName('Float special compare test')
            ->setTimelimit(5)
            ->setCompareExecutable($manager->getRepository(Executable::class)->find('compare'))
            ->setSpecialCompareArgs('float_tolerance 1E-6');
        if (file_exists($examplesDir . 'fltcmp.pdf')) {
            $fltcmp
                ->setProblemtextType('pdf')
                ->setProblemtext(file_get_contents($examplesDir . 'fltcmp.pdf'));
        }

        $boolfind = new Problem();
        $boolfind
            ->setExternalid('boolfind')
            ->setName('Boolean switch search')
            ->setTimelimit(5)
            ->setRunExecutable($this->getReference(ExecutableFixture::BOOLFIND_RUN_REFERENCE))
            ->setCompareExecutable($this->getReference(ExecutableFixture::BOOLFIND_CMP_REFERENCE));
        if (file_exists($examplesDir . 'boolfind.pdf')) {
            $boolfind
                ->setProblemtextType('pdf')
                ->setProblemtext(file_get_contents($examplesDir . 'boolfind.pdf'));
        }

        $manager->persist($hello);
        $manager->persist($fltcmp);
        $manager->persist($boolfind);
        $manager->flush();

        $this->addReference(self::HELLO_REFERENCE, $hello);
        $this->addReference(self::FLTCMP_REFERENCE, $fltcmp);
        $this->addReference(self::BOOLFIND_REFERENCE, $boolfind);
    }

    /**
     * @inheritDoc
     */
    public function getDependencies()
    {
        return [ExecutableFixture::class];
    }
}
