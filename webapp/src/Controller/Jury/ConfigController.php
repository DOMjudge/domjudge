<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Entity\Configuration;
use App\Service\CheckConfigService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jury/config")
 * @IsGranted("ROLE_ADMIN")
 */
class ConfigController extends AbstractController
{
    protected EntityManagerInterface $em;
    protected LoggerInterface $logger;
    protected DOMJudgeService $dj;
    protected CheckConfigService $checkConfigService;
    protected ConfigurationService $config;

    public function __construct(
        EntityManagerInterface $em,
        LoggerInterface $logger,
        DOMJudgeService $dj,
        CheckConfigService $checkConfigService,
        ConfigurationService $config
    ) {
        $this->em                 = $em;
        $this->logger             = $logger;
        $this->dj                 = $dj;
        $this->checkConfigService = $checkConfigService;
        $this->config             = $config;
    }

    /**
     * @Route("", name="jury_config")
     */
    public function indexAction(EventLogService $eventLogService, Request $request): Response
    {
        $specs = $this->config->getConfigSpecification();
        foreach ($specs as &$spec) {
            $spec = $this->config->addOptions($spec);
        }
        unset($spec);
        /** @var Configuration[] $options */
        $options = $this->em->createQueryBuilder()
            ->from(Configuration::class, 'c', 'c.name')
            ->select('c')
            ->getQuery()
            ->getResult();
        if ($request->getMethod() == 'POST' && $request->request->has('save')) {
            $this->addFlash('scoreboard_refresh', 'After changing specific ' .
                            'settings, you might need to refresh the scoreboard.');

            $data = [];
            foreach ($request->request->all() as $key => $value) {
                if (strpos($key, 'config_') === 0) {
                    $valueToUse = $value;
                    if (is_array($value)) {
                        $firstItem = reset($value);
                        if (is_array($firstItem) && isset($firstItem['key'])) {
                            $valueToUse = [];
                            foreach ($value as $item) {
                                $valueToUse[$item['key']] = $item['val'];
                            }
                        }
                    }
                    $data[substr($key, strlen('config_'))] = $valueToUse;
                    if ($key === 'config_lazy_eval_results' && $value !== DOMJudgeService::EVAL_DEMAND) {
                        $this->dj->unblockJudgeTasks();
                    }
                }
            }
            $this->config->saveChanges($data, $eventLogService, $this->dj);
            return $this->redirectToRoute('jury_config');
        }

        $categories = [];
        foreach ($specs as $spec) {
            if (!in_array($spec['category'], $categories)) {
                $categories[] = $spec['category'];
            }
        }
        $allData = [];
        foreach ($categories as $category) {
            $data = [];
            foreach ($specs as $specName => $spec) {
                if ($spec['category'] !== $category) {
                    continue;
                }
                $data[] = [
                    'name' => $specName,
                    'type' => $spec['type'],
                    'value' => isset($options[$specName]) ?
                        $options[$specName]->getValue() :
                        $spec['default_value'],
                    'description' => $spec['description'],
                    'options' => $spec['options'] ?? null,
                    'key_options' => $spec['key_options'] ?? null,
                    'value_options' => $spec['value_options'] ?? null,
                ];
            }
            $allData[] = [
                'name' => $category,
                'data' => $data
            ];
        }
        return $this->render('jury/config.html.twig', [
            'options' => $allData,
        ]);
    }

    /**
     * @Route("/check", name="jury_config_check")
     */
    public function checkAction(string $projectDir, string $logsDir): Response
    {
        $results = $this->checkConfigService->runAll();
        $stopwatch = $this->checkConfigService->getStopwatch();
        return $this->render('jury/config_check.html.twig', [
            'results' => $results,
            'stopwatch' => $stopwatch,
            'dir' => [
                    'project' => dirname($projectDir),
                    'log' => $logsDir,
                ],
        ]);
    }
}
