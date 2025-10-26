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
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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

    /**
     * @return array{
     *     options: list<array{
     *         name: string,
     *         data: list<array{
     *             name: string,
     *             type: string,
     *             value: mixed,
     *             description: string,
     *             options: array<int|string, string>|null,
     *             key_options: array<int|string, string>|null,
     *             value_options: array<int|string, string>|null,
     *             key_placeholder: string,
     *             value_placeholder: string
     *         }>
     *     }>,
     *     errors: array<string, string>,
     *     activeCategory: string,
     *     diffs: array<string, array{before: mixed, after: mixed}>|null
     * }|RedirectResponse
     */
    #[Route(path: '', name: 'jury_config')]
    #[Template(template: 'jury/config.html.twig')]
    public function indexAction(EventLogService $eventLogService, Request $request): array|RedirectResponse
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
            $before = $this->config->all();
            // In case we clear a value it would not be sent and we keep the old value, this is a mistake
            foreach ($before as $key => $value) {
                if (!isset($data[$key])) {
                    if (is_array($value)) {
                        $data[$key] = [];
                    } else {
                        $data[$key] = null;
                    }
                }
            }
            $errors = $this->config->saveChanges($data, $eventLogService, $this->dj, options: $options);
            $after = $this->config->all();

            // Compile a list of differences.
            $diffs = [];
            foreach ($before as $key => $value) {
                if (!array_key_exists($key, $after)) {
                    $diffs[$key] = ['before' => $value, 'after' => null];
                } elseif ($value !== $after[$key]) {
                    $diffs[$key] = ['before' => $value, 'after' => $after[$key]];
                }
            }
            foreach ($after as $key => $value) {
                if (!array_key_exists($key, $before)) {
                    $diffs[$key] = ['before' => null, 'after' => $value];
                }
            }

            if (empty($errors)) {
                $needsRefresh = false;
                $needsRejudging = false;
                foreach ($diffs as $key => $diff) {
                    $category = $this->config->getCategory($key);
                    if ($category === 'Scoring') {
                        $needsRefresh = true;
                    }
                    if ($category === 'Judging') {
                        $needsRejudging = true;
                    }
                }

                if ($needsRefresh) {
                    $this->addFlash('scoreboard_refresh', 'After changing specific ' .
                        'scoring related settings, you might need to refresh the scoreboard (cache).');
                }
                if ($needsRejudging) {
                    $this->addFlash('danger', 'After changing specific ' .
                        'judging related settings, you might need to rejudge affected submissions.');
                }

                return $this->redirectToRoute('jury_config', ['diffs' => json_encode($diffs)]);
            } else {
                $this->addFlash('danger', 'Some errors occurred while saving configuration, ' .
                    'please check the data you entered.');
            }
        }

        $categories = [];
        foreach ($specs as $spec) {
            if (!in_array($spec->category, $categories)) {
                $categories[] = $spec->category;
            }
        }
        $allData = [];
        $activeCategory = null;
        foreach ($categories as $category) {
            $data = [];
            foreach ($specs as $specName => $spec) {
                if ($spec->category !== $category) {
                    continue;
                }
                if (isset($errors[$specName]) && $activeCategory === null) {
                    $activeCategory = $category;
                }
                $data[] = [
                    'name' => $specName,
                    'type' => $spec->type,
                    'value' => isset($options[$specName]) ?
                        $options[$specName]->getValue() :
                        $spec->defaultValue,
                    'description' => $spec->description,
                    'options' => $spec->options,
                    'key_options' => $spec->keyOptions,
                    'value_options' => $spec->valueOptions,
                    'key_placeholder' => $spec->keyPlaceholder ?? '',
                    'value_placeholder' => $spec->valuePlaceholder ?? '',
                ];
            }
            $allData[] = [
                'name' => $category,
                'data' => $data
            ];
        }
        $diffs = $request->query->get('diffs');
        if ($diffs !== null) {
            $diffs = json_decode($diffs, true);
        }
        return [
            'options' => $allData,
            'errors' => $errors ?? [],
            'activeCategory' => $activeCategory ?? 'Scoring',
            'diffs' => $diffs,
        ];
    }

    /**
     * @return array{
     *     results: array<string, mixed>,
     *     stopwatch: mixed,
     *     dir: array{project: string, log: string},
     *     logFilesWithSize: array<string, string>
     * }
     */
    #[Route(path: '/check', name: 'jury_config_check')]
    #[Template(template: 'jury/config_check.html.twig')]
    public function checkAction(
        #[Autowire('%kernel.project_dir%')]
        string $projectDir,
        #[Autowire('%kernel.logs_dir%')]
        string $logsDir
    ): array {
        $results = $this->checkConfigService->runAll();
        $stopwatch = $this->checkConfigService->getStopwatch();
        $logFiles = glob($logsDir . '/*.log');
        $logFilesWithSize = [];
        foreach ($logFiles as $logFile) {
            $logFilesWithSize[str_replace($logsDir . '/', '', $logFile)] = Utils::printsize(filesize($logFile));
        }
        return [
            'results' => $results,
            'stopwatch' => $stopwatch,
            'dir' => [
                'project' => dirname($projectDir),
                'log' => $logsDir,
            ],
            'logFilesWithSize' => $logFilesWithSize,
        ];
    }

    /**
     * @return array{
     *     logFile: string,
     *     contents: string
     * }
     */
    #[Route(path: '/tail-log/{logFile<[a-z0-9-]+\.log>}', name: 'jury_tail_log')]
    #[Template(template: 'jury/tail_log.html.twig')]
    public function tailLogAction(
        string $logFile,
        #[Autowire('%kernel.logs_dir%')]
        string $logsDir
    ): array {
        $fullFile = "$logsDir/$logFile";
        $command = sprintf('tail -n200 %s', escapeshellarg($fullFile));
        exec($command, $lines);
        return [
            'logFile' => $logFile,
            'contents' => implode("\n", $lines),
        ];
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
