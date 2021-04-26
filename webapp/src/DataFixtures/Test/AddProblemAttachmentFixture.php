<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Problem;
use App\Entity\ProblemAttachment;
use App\Entity\ProblemAttachmentContent;
use Doctrine\Persistence\ObjectManager;

class AddProblemAttachmentFixture extends AbstractTestDataFixture
{
    public function load(ObjectManager $manager)
    {
        $problem = $manager->getRepository(Problem::class)->findOneBy(['externalid' => 'boolfind']);
        $attachment = (new ProblemAttachment())
            ->setName('interactor')
            ->setType('py');
        $manager->persist($attachment);
        $content = (new ProblemAttachmentContent())
            ->setContent(file_get_contents("testdata/boolfind.py"))
            ->setAttachment($attachment);
        $manager->persist($content);
        $problem = $problem->addAttachment($attachment);
        $manager->persist($problem);
        $manager->flush();
    }
}
