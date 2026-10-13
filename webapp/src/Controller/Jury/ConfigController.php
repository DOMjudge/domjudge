<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Attribute\ReleaseSessionLock;
use App\Controller\API\AbstractRestController;
use App\Form\Type\ConfigurationType;
use App\Service\CheckConfigService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

    #[Route(path: '', name: 'jury_config')]
    public function indexAction(EventLogService $eventLogService, Request $request): Response
    {
        $form = $this->createForm(ConfigurationType::class, $this->config->all());
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $before = $this->config->all();
                $errors = $this->config->saveChanges($form->getData(), $eventLogService, $this->dj, upgradedContests: $upgradedContests);
                foreach ($errors as $name => $error) {
                    $form->get($name)->addError(new FormError($error));
                }
                if (empty($errors)) {
                    $diffs = $this->compileDiffs($before, $this->config->all());
                    $changedCategories = array_map($this->config->getCategory(...), array_keys($diffs));
                    if (in_array('Scoring', $changedCategories, true)) {
                        $this->addFlash('scoreboard_refresh', 'After changing specific ' .
                            'scoring related settings, you might need to refresh the scoreboard (cache).');
                    }
                    if (in_array('Judging', $changedCategories, true)) {
                        $this->addFlash('danger', 'After changing specific ' .
                            'judging related settings, you might need to rejudge affected submissions.');
                    }
                    if (!empty($upgradedContests)) {
                        $this->addFlash('info', sprintf(
                            'Upgraded the event feed of contests %s to the new CCS API version. ' .
                            'Event feed clients need to reconnect without a since_token.',
                            implode(', ', $upgradedContests)
                        ));
                    }
                    return $this->redirectToRoute('jury_config', ['diffs' => json_encode($diffs)]);
                }
            }
            $this->addFlash('danger', 'Some errors occurred while saving configuration, ' .
                'please check the data you entered.');
        }

        if (((int)$this->config->get('minimum_number_of_balloons')) !== 0) {
            $this->addFlash('warning', 'Minimum number of balloons is enabled, this leads to data inconsistencies and/or information leaking during the freeze. Can be disabled in the "Display" tab.');
        }

        $categories = [];
        $activeCategory = null;
        foreach ($this->config->getConfigSpecification() as $name => $spec) {
            $categories[$spec->category][] = $name;
            if ($activeCategory === null && $form->get($name)->getErrors(true)->count() > 0) {
                $activeCategory = $spec->category;
            }
        }

        $diffs = $request->query->get('diffs');
        if ($diffs !== null) {
            $diffs = json_decode($diffs, true);
        }
        return $this->render('jury/config.html.twig', [
            'form' => $form,
            'categories' => $categories,
            'activeCategory' => $activeCategory ?? array_key_first($categories),
            'diffs' => $diffs,
        ]);
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @return array<string, array{before: mixed, after: mixed}>
     */
    private function compileDiffs(array $before, array $after): array
    {
        $diffs = [];
        foreach ($before + $after as $key => $value) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;
            if ($old !== $new) {
                $diffs[$key] = ['before' => $old, 'after' => $new];
            }
        }
        return $diffs;
    }

    #[Route(path: '/check', name: 'jury_config_check')]
    #[ReleaseSessionLock]
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
