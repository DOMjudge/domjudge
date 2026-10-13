<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Entity\Contest;
use App\Service\ImportProblemService;
use App\Tests\Unit\BaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
use ZipArchive;

class ProblemInteractionSampleTest extends BaseTestCase
{
    protected array $roles = ['admin'];

    public function testInteractionShownInJuryTestcases(): void
    {
        $interaction = ">1 2\n<3\n---\n>4 5\n<9\n";
        $tmpFile = tempnam(sys_get_temp_dir(), 'dj-') . '.zip';
        $zip = new ZipArchive();
        $zip->open($tmpFile, ZipArchive::CREATE);
        $zip->addFromString('problem.yaml', "name: interactiontest\ntype: pass-fail interactive\n");
        $zip->addFromString('output_validators/val/run.sh', "#!/bin/sh\nexit 42\n");
        $zip->addFromString('data/sample/1.in', "1 2\n");
        $zip->addFromString('data/sample/1.ans', "3\n");
        $zip->addFromString('data/sample/1.interaction', $interaction);
        $zip->close();

        $zip = new ZipArchive();
        $zip->open($tmpFile);
        $messages = ['info' => [], 'warning' => [], 'danger' => []];
        $contest = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $problem = static::getContainer()->get(ImportProblemService::class)
            ->importZippedProblem($zip, 'interactiontest.zip', null, $contest, $messages);
        $zip->close();
        unlink($tmpFile);
        self::assertNotNull($problem, implode('; ', $messages['danger']));

        $extId = $problem->getExternalid();

        // The testcases overview must link to the interaction log.
        $crawler = $this->client->request('GET', '/jury/problems/' . $extId . '/testcases');
        self::assertEquals(200, $this->client->getInternalResponse()->getStatusCode());
        $html = $this->client->getInternalResponse()->getContent();
        self::assertStringContainsString('.interaction', $html, 'testcases page should show the interaction file');
        self::assertStringContainsString('/fetch/interaction', $html, 'testcases page should link the fetch route');

        // And fetching it must return the stored log verbatim.
        $this->client->request('GET', '/jury/problems/' . $extId . '/testcases/1/fetch/interaction');
        self::assertEquals(200, $this->client->getInternalResponse()->getStatusCode());
        self::assertEquals($interaction, $this->client->getInternalResponse()->getContent());
    }
}
