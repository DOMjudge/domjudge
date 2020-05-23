<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Executable;
use Doctrine\Persistence\ObjectManager;

/**
 * Class ExecutableFixture
 * @package App\DataFixtures
 */
class ExecutableFixture extends AbstractExampleDataFixture
{
    const BOOLFIND_CMP_REFERENCE = 'boolfind-cmp';
    const BOOLFIND_RUN_REFERENCE = 'boolfind-run';

    /**
     * @var string
     */
    protected $sqlDir;

    /**
     * ExecutableFixture constructor.
     * @param string $sqlDir
     */
    public function __construct(string $sqlDir)
    {
        $this->sqlDir = $sqlDir;
    }

    /**
     * @inheritDoc
     */
    public function load(ObjectManager $manager)
    {
        $boolfindCompareFile = sprintf(
            '%s/files/examples/boolfind_cmp.zip',
            $this->sqlDir
        );
        $boolfindCompare     = new Executable();
        $boolfindCompare
            ->setExecid('boolfind_cmp')
            ->setDescription('boolfind comparator')
            ->setType('compare')
            ->setMd5sum(md5_file($boolfindCompareFile))
            ->setZipfile(file_get_contents($boolfindCompareFile));

        $boolfindRunFile = sprintf(
            '%s/files/examples/boolfind_run.zip',
            $this->sqlDir
        );
        $boolfindRun     = new Executable();
        $boolfindRun
            ->setExecid('boolfind_run')
            ->setDescription('boolfind run script')
            ->setType('run')
            ->setMd5sum(md5_file($boolfindRunFile))
            ->setZipfile(file_get_contents($boolfindRunFile));

        $manager->persist($boolfindCompare);
        $manager->persist($boolfindRun);
        $manager->flush();

        $this->addReference(self::BOOLFIND_CMP_REFERENCE, $boolfindCompare);
        $this->addReference(self::BOOLFIND_RUN_REFERENCE, $boolfindRun);
    }
}
