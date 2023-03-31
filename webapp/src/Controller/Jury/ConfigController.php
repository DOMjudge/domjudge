<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Entity\Configuration;
use App\Service\CheckConfigService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[IsGranted('ROLE_ADMIN')]
#[Route(path: '/jury/config')]
class ConfigController extends AbstractController
{
    public function __construct(
        protected readonly EntityManagerInterface $em,
        protected readonly LoggerInterface $logger,
        protected readonly DOMJudgeService $dj,
        protected readonly CheckConfigService $checkConfigService,
        protected readonly ConfigurationService $config
    ) {}

    #[Route(path: '', name: 'jury_config')]
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
                if (str_starts_with($key, 'config_')) {
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

    #[Route(path: '/check', name: 'jury_config_check')]
    public function checkAction(
        #[Autowire('%kernel.project_dir%')]
        string $projectDir,
        #[Autowire('%kernel.logs_dir%')]
        string $logsDir
    ): Response {
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
