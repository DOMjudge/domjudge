<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\JudgehostRestriction;
use App\Entity\Problem;
use Doctrine\Persistence\ObjectManager;

class AddJudgehostRestrictionFixture extends AbstractTestDataFixture
{
    public function load(ObjectManager $manager)
    {
        $problem = $manager->getRepository(Problem::class)->findOneBy(['externalid' => 'boolfind']);
        $restriction = (new JudgehostRestriction())
            ->setName('TestRestriction')
            ->setProblems([$problem])
            ->setRejudgeOwn(True);
        $manager->persist($restriction);
        $manager->flush();
    }
}
