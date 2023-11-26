<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\API\AbstractRestController;
use App\Entity\Configuration;
use App\Service\CheckConfigService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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
            $errors = $this->config->saveChanges($data, $eventLogService, $this->dj, $options);

            if (empty($errors)) {
                $this->addFlash('scoreboard_refresh', 'After changing specific ' .
                    'settings, you might need to refresh the scoreboard.');

                return $this->redirectToRoute('jury_config');
            } else {
                $this->addFlash('danger', 'Some errors occurred while saving configuration, ' .
                    'please check the data you entered.');
            }
        }

        $categories = [];
        foreach ($specs as $spec) {
            if (!in_array($spec['category'], $categories)) {
                $categories[] = $spec['category'];
            }
        }
        $allData = [];
        $activeCategory = null;
        foreach ($categories as $category) {
            $data = [];
            foreach ($specs as $specName => $spec) {
                if ($spec['category'] !== $category) {
                    continue;
                }
                if (isset($errors[$specName]) && $activeCategory === null) {
                    $activeCategory = $category;
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
                    'key_placeholder' => $spec['key_placeholder'] ?? '',
                    'value_placeholder' => $spec['value_placeholder'] ?? '',
                ];
            }
            $allData[] = [
                'name' => $category,
                'data' => $data
            ];
        }
        return $this->render('jury/config.html.twig', [
            'options' => $allData,
            'errors' => $errors ?? [],
            'activeCategory' => $activeCategory ?? 'Scoring',
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
        $logFiles = glob($logsDir . '/*.log');
        $logFilesWithSize = [];
        foreach ($logFiles as $logFile) {
            $logFilesWithSize[str_replace($logsDir . '/', '', $logFile)] = Utils::printsize(filesize($logFile));
        }
        return $this->render('jury/config_check.html.twig', [
            'results' => $results,
            'stopwatch' => $stopwatch,
            'dir' => [
                'project' => dirname($projectDir),
                'log' => $logsDir,
            ],
            'logFilesWithSize' => $logFilesWithSize,
        ]);
    }

    #[Route(path: '/tail-log/{logFile<[a-z0-9-]+\.log>}', name: 'jury_tail_log')]
    public function tailLogAction(
        string $logFile,
        #[Autowire('%kernel.logs_dir%')]
        string $logsDir
    ): Response {
        $fullFile = "$logsDir/$logFile";
        $command = sprintf('tail -n200 %s', escapeshellarg($fullFile));
        exec($command, $lines);
        return $this->render('jury/tail_log.html.twig', [
            'logFile' => $logFile,
            'contents' => implode("\n", $lines),
        ]);
    }

    #[Route(path: '/download-log/{logFile<[a-z0-9-]+\.log>}', name: 'jury_download_log')]
    public function downloadLogAction(
        Request $request,
        string $logFile,
        #[Autowire('%kernel.logs_dir%')]
        string $logsDir
    ): BinaryFileResponse {
        $fullFile = "$logsDir/$logFile";
        return AbstractRestController::sendBinaryFileResponse($request, $fullFile, true);
    }
}
