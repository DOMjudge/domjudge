<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Testcase;
use App\Entity\TestcaseContent;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Class TestcaseFixture
 * @package App\DataFixtures
 */
class TestcaseFixture extends AbstractExampleDataFixture implements DependentFixtureInterface
{
    /**
     * @inheritDoc
     */
    public function load(ObjectManager $manager)
    {
        $this->addTestcase(
            $manager,
            ProblemFixture::HELLO_REFERENCE,
            1,
            "1\n",
            "Hello world!\n"
        );

        $this->addTestcase(
            $manager,
            ProblemFixture::FLTCMP_REFERENCE,
            1,
            "3\n1.0\n2E0\n3\n",
            "1.0\n0.50000000001\n3.333333333E-1\n",
            'Different floating formats'
        );

        $this->addTestcase(
            $manager,
            ProblemFixture::FLTCMP_REFERENCE,
            2,
            "2\n4.0000000000000\n5.0000000000001\n",
            "0.25\n2E-1\n",
            'High precision inputs'
        );

        $this->addTestcase(
            $manager,
            ProblemFixture::FLTCMP_REFERENCE,
            3,
            "3\n+0\nInf\nnan\n",
            "inf\n0\nNaN\n",
            'Inf/NaN checks'
        );

        $this->addTestcase(
            $manager,
            ProblemFixture::BOOLFIND_REFERENCE,
            1,
            "1\n5\n1\n1\n0\n1\n0\n",
            "OUTPUT 1\n"
        );
    }

    /**
     * Add a testcase
     * @param ObjectManager $manager
     * @param string        $problem
     * @param int           $rank
     * @param string        $input
     * @param string        $output
     * @param string|null   $description
     */
    protected function addTestcase(
        ObjectManager $manager,
        string $problem,
        int $rank,
        string $input,
        string $output,
        ?string $description = null
    ) {
        $testcase = new Testcase();
        $content  = new TestcaseContent();
        $testcase
            ->setProblem($this->getReference($problem))
            ->setRank($rank)
            ->setMd5sumInput(md5($input))
            ->setMd5sumOutput(md5($output))
            ->setDescription($description)
            ->setContent($content);
        $content
            ->setInput($input)
            ->setOutput($output);

        $manager->persist($testcase);
        $manager->flush();
    }

    /**
     * @inheritDoc
     */
    public function getDependencies()
    {
        return [ProblemFixture::class];
    }
}
