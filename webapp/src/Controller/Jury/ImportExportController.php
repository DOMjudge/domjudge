<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Entity\Clarification;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Problem;
use App\Entity\TeamCategory;
use App\Form\Type\ContestExportType;
use App\Form\Type\ContestImportType;
use App\Form\Type\ExportResultsType;
use App\Form\Type\ICPCCmsType;
use App\Form\Type\JsonImportType;
use App\Form\Type\ProblemsImportType;
use App\Form\Type\ProblemUploadType;
use App\Form\Type\TsvImportType;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ICPCCmsService;
use App\Service\ImportExportService;
use App\Service\ImportProblemService;
use App\Service\ScoreboardService;
use App\Utils\Scoreboard\Filter;
use App\Utils\Utils;
use Collator;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Twig\Environment;

#[Route(path: '/jury/import-export')]
#[IsGranted('ROLE_JURY')]
class ImportExportController extends BaseController
{
    public function __construct(
        protected readonly ICPCCmsService $icpcCmsService,
        protected readonly ImportExportService $importExportService,
        EntityManagerInterface $em,
        protected readonly ScoreboardService $scoreboardService,
        DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        protected readonly EventLogService $eventLogService,
        protected readonly ImportProblemService $importProblemService,
        KernelInterface $kernel,
        #[Autowire('%domjudge.version%')]
        protected readonly string $domjudgeVersion,
        protected readonly Environment $twig,
    ) {
        parent::__construct($em, $eventLogService, $dj, $kernel);
    }

    /**
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    #[Route(path: '', name: 'jury_import_export')]
    #[IsGranted('ROLE_ADMIN')]
    public function indexAction(Request $request): Response
    {
        $tsvForm = $this->createForm(TsvImportType::class);

        $tsvForm->handleRequest($request);

        if ($tsvForm->isSubmitted() && $tsvForm->isValid()) {
            $type  = $tsvForm->get('type')->getData();
            $file  = $tsvForm->get('file')->getData();
            $count = $this->importExportService->importTsv($type, $file, $message);
            if ($count >= 0) {
                $this->addFlash('success', sprintf('%d items imported', $count));
            } else {
                $this->addFlash('danger', $message);
            }
            return $this->redirectToRoute('jury_import_export');
        }

        $jsonForm = $this->createForm(JsonImportType::class);

        $jsonForm->handleRequest($request);

        if ($jsonForm->isSubmitted() && $jsonForm->isValid()) {
            $type  = $jsonForm->get('type')->getData();
            $file  = $jsonForm->get('file')->getData();
            $count = $this->importExportService->importJson($type, $file, $message);
            if ($count >= 0) {
                $this->addFlash('success', sprintf('%d items imported', $count));
            } else {
                $this->addFlash('danger', $message);
            }
            return $this->redirectToRoute('jury_import_export');
        }

        $icpcCmsForm = $this->createForm(ICPCCmsType::class);

        $icpcCmsForm->handleRequest($request);

        if ($icpcCmsForm->isSubmitted() && $icpcCmsForm->isValid()) {
            $contestId   = $icpcCmsForm->get('contest_id')->getData();
            $accessToken = $icpcCmsForm->get('access_token')->getData();
            /** @var SubmitButton $fetchTeams */
            $fetchTeams = $icpcCmsForm->get('fetch_teams');
            if ($fetchTeams->isClicked()) {
                if ($this->icpcCmsService->importTeams($accessToken, $contestId, $message)) {
                    $this->addFlash('success', 'Teams successfully imported');
                } else {
                    $this->addFlash('danger', $message);
                }
            } else {
                if ($this->icpcCmsService->uploadStandings($accessToken, $contestId, $message)) {
                    $this->addFlash('success', 'Standings successfully uploaded');
                } else {
                    $this->addFlash('danger', $message);
                }
            }
            return $this->redirectToRoute('jury_import_export');
        }

        $currentContestFormData = [
            'contest' => $this->dj->getCurrentContest(),
        ];
        $problemForm = $this->createForm(ProblemUploadType::class, $currentContestFormData);

        $problemForm->handleRequest($request);

        if ($problemForm->isSubmitted() && $problemForm->isValid()) {
            $problemFormData = $problemForm->getData();

            /** @var UploadedFile $archive */
            $archive = $problemFormData['archive'];
            /** @var Problem|null $newProblem */
            $newProblem = null;
            /** @var Contest|null $contest */
            $contest = $problemFormData['contest'] ?? null;
            $contestId = $contest?->getCid();
            $allMessages = [];
            try {
                $zip = $this->dj->openZipFile($archive->getRealPath());
                $clientName = $archive->getClientOriginalName();
                $messages = [];
                if ($contestId === null) {
                    $contest = null;
                } else {
                    $contest = $this->em->getRepository(Contest::class)->find($contestId);
                }
                $newProblem = $this->importProblemService->importZippedProblem(
                    $zip, $clientName, null, $contest, $messages
                );
                $allMessages = array_merge($allMessages, $messages);
                if ($newProblem) {
                    $this->dj->auditlog('problem', $newProblem->getProbid(), 'upload zip',
                        $clientName);
                } else {
                    $this->postMessages($allMessages);
                    return $this->redirectToRoute('jury_problems');
                }
            } catch (Exception $e) {
                $allMessages['danger'][] = $e->getMessage();
            } finally {
                if (isset($zip)) {
                    $zip->close();
                }
            }
            $this->postMessages($allMessages);

            if ($newProblem !== null) {
                return $this->redirectToRoute('jury_problem', ['probId' => $newProblem->getProbid()]);
            } else {
                return $this->redirectToRoute('jury_problems');
            }
        }

        $contestExportForm = $this->createForm(ContestExportType::class, $currentContestFormData);

        $contestExportForm->handleRequest($request);

        if ($contestExportForm->isSubmitted() && $contestExportForm->isValid()) {
            /** @var Contest $contest */
            $contest  = $contestExportForm->get('contest')->getData();
            $response = new StreamedResponse();
            $response->setCallback(function () use ($contest) {
                echo Yaml::dump($this->importExportService->getContestYamlData($contest), 3);
            });
            $response->headers->set('Content-Type', 'application/x-yaml');
            $response->headers->set('Content-Disposition', 'attachment; filename="contest.yaml"');
            $response->headers->set('Content-Transfer-Encoding', 'binary');
            $response->headers->set('Connection', 'Keep-Alive');
            $response->headers->set('Accept-Ranges', 'bytes');

            return $response;
        }

        $contestImportForm = $this->createForm(ContestImportType::class);

        $contestImportForm->handleRequest($request);

        if ($contestImportForm->isSubmitted() && $contestImportForm->isValid()) {
            /** @var UploadedFile $file */
            $file = $contestImportForm->get('file')->getData();
            try {
                $data = Yaml::parseFile($file->getRealPath(), Yaml::PARSE_DATETIME);
            } catch (ParseException $e) {
                $this->addFlash('danger', "Parse error in YAML/JSON file (" . $file->getClientOriginalName() . "): " . $e->getMessage());
                return $this->redirectToRoute('jury_import_export');
            }
            if ($this->importExportService->importContestData($data, $message, $cid)) {
                $this->addFlash('success',
                                sprintf('The file %s is successfully imported.', $file->getClientOriginalName()));
            } else {
                $this->addFlash('danger', $message);
            }
            return $this->redirectToRoute('jury_import_export');
        }

        $problemsImportForm = $this->createForm(ProblemsImportType::class);

        $problemsImportForm->handleRequest($request);

        if ($problemsImportForm->isSubmitted() && $problemsImportForm->isValid()) {
            /** @var UploadedFile $file */
            $file = $problemsImportForm->get('file')->getData();
            try {
                $data = Yaml::parseFile($file->getRealPath(), Yaml::PARSE_DATETIME);
            } catch (ParseException $e) {
                $this->addFlash('danger', "Parse error in YAML/JSON file (" . $file->getClientOriginalName() . "): " . $e->getMessage());
                return $this->redirectToRoute('jury_import_export');
            }
            if ($this->importExportService->importProblemsData($problemsImportForm->get('contest')->getData(), $data, $ids, $messages)) {
                $this->addFlash('success',
                    sprintf('The file %s is successfully imported.', $file->getClientOriginalName()));
            } else {
                if (!empty($messages)) {
                    $this->postMessages($messages);
                } else {
                    $this->addFlash('danger', 'Failed importing problems');
                }
            }
            return $this->redirectToRoute('jury_import_export');
        }

        $exportResultsForm = $this->createForm(ExportResultsType::class);

        $exportResultsForm->handleRequest($request);

        if ($exportResultsForm->isSubmitted() && $exportResultsForm->isValid()) {
            $contest = $this->dj->getCurrentContest();
            if ($contest === null) {
                throw new BadRequestHttpException('No current contest');
            }

            $data = $exportResultsForm->getData();
            $format = $data['format'];
            $sortOrder = $data['sortorder'];
            $individuallyRanked = $data['individually_ranked'];
            $honors = $data['honors'];

            $extension = match ($format) {
                'html_inline', 'html_download' => 'html',
                'tsv' => 'tsv',
                default => throw new BadRequestHttpException('Invalid format'),
            };
            $contentType = match ($format) {
                'html_inline' => 'text/html',
                'html_download' => 'text/html',
                'tsv' => 'text/csv',
                default => throw new BadRequestHttpException('Invalid format'),
            };
            $contentDisposition = match ($format) {
                'html_inline' => 'inline',
                'html_download', 'tsv' => 'attachment',
                default => throw new BadRequestHttpException('Invalid format'),
            };
            $filename = 'results.' . $extension;

            $response = new StreamedResponse();
            $response->setCallback(function () use (
                $format,
                $sortOrder,
                $individuallyRanked,
                $honors
            ) {
                if ($format === 'tsv') {
                    $data = $this->importExportService->getResultsData(
                        $sortOrder->sort_order,
                        $individuallyRanked,
                        $honors,
                    );

                    echo "results\t1\n";
                    foreach ($data as $row) {
                        echo implode("\t", array_map(fn($field) => Utils::toTsvField((string)$field), $row->toArray())) . "\n";
                    }
                } else {
                    echo $this->getResultsHtml(
                        $sortOrder->sort_order,
                        $individuallyRanked,
                        $honors,
                    );
                }
            });
            $response->headers->set('Content-Type', $contentType);
            $response->headers->set('Content-Disposition', "$contentDisposition; filename=\"$filename\"");
            $response->headers->set('Content-Transfer-Encoding', 'binary');
            $response->headers->set('Connection', 'Keep-Alive');
            $response->headers->set('Accept-Ranges', 'bytes');

            return $response;
        }

        return $this->render('jury/import_export.html.twig', [
            'tsv_form' => $tsvForm,
            'json_form' => $jsonForm,
            'icpccms_form' => $icpcCmsForm,
            'problem_form' => $problemForm,
            'contest_export_form' => $contestExportForm,
            'contest_import_form' => $contestImportForm,
            'problems_import_form' => $problemsImportForm,
            'export_results_form' => $exportResultsForm,
        ]);
    }

    #[Route(path: '/export/{type<groups|teams|wf_results|full_results>}.tsv', name: 'jury_tsv_export')]
    public function exportTsvAction(string $type): Response
    {
        $data    = [];
        $tsvType = $type;
        try {
            switch ($type) {
                case 'groups':
                    $data = $this->importExportService->getGroupData();
                    break;
                case 'teams':
                    $data = $this->importExportService->getTeamData();
                    break;
            }
        } catch (BadRequestHttpException $e) {
            $this->addFlash('danger', $e->getMessage());
            return $this->redirectToRoute('jury_import_export');
        }

        $response = new StreamedResponse();
        $response->setCallback(function () use ($tsvType, $data) {
            $version = 1;
            echo sprintf("%s\t%s\n", $tsvType, $version);
            foreach ($data as $row) {
                // Utils::toTsvFields handles escaping of reserved characters.
                echo implode("\t", array_map(fn($field) => Utils::toTsvField((string)$field), $row)) . "\n";
            }
        });
        $filename = sprintf('%s.tsv', $type);
        $response->headers->set('Content-Type', sprintf('text/plain; name="%s"; charset=utf-8', $filename));
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));

        return $response;
    }

    #[Route(path: '/export/clarifications.html', name: 'jury_html_export_clarifications')]
    public function exportClarificationsHtmlAction(): Response
    {
        try {
            return $this->getClarificationsHtml();
        } catch (BadRequestHttpException $e) {
            $this->addFlash('danger', $e->getMessage());
            return $this->redirectToRoute('jury_import_export');
        }
    }

    protected function getResultsHtml(
        int $sortOrder,
        bool $individuallyRanked,
        bool $honors
    ): string {
        /** @var TeamCategory[] $categories */
        $categories  = $this->em->createQueryBuilder()
            ->from(TeamCategory::class, 'c', 'c.categoryid')
            ->select('c')
            ->where('c.visible = 1')
            ->getQuery()
            ->getResult();
        $categoryIds = [];
        foreach ($categories as $category) {
            $categoryIds[] = $category->getCategoryid();
        }

        $contest = $this->dj->getCurrentContest();
        if ($contest === null) {
            throw new BadRequestHttpException('No current contest');
        }

        $scoreIsInSeconds = (bool)$this->config->get('score_in_seconds');
        $filter           = new Filter();
        $filter->categories = $categoryIds;
        $scoreboard = $this->scoreboardService->getScoreboard($contest, true, $filter);
        $teams      = $scoreboard->getTeams();

        $teamNames = [];
        foreach ($teams as $team) {
            $teamNames[$team->getIcpcId()] = $team->getEffectiveName();
        }

        $awarded       = [];
        $ranked        = [];
        $honorable     = [];
        $regionWinners = [];
        $rankPerTeam   = [];

        foreach ($this->importExportService->getResultsData($sortOrder, $individuallyRanked, $honors) as $row) {
            $team = $teamNames[$row->teamId];
            $rankPerTeam[$row->teamId] = $row->rank;

            if ($row->groupWinner) {
                $regionWinners[] = [
                    'group' => $row->groupWinner,
                    'team' => $team,
                    'rank' => $row->rank ?? '-',
                ];
            }

            $row = [
                'team' => $team,
                'rank' => $row->rank,
                'award' => $row->award,
                'solved' => $row->numSolved,
                'total_time' => $row->totalTime,
                'max_time' => $row->timeOfLastSubmission,
            ];
            if (preg_match('/^(.*) Medal$/', $row['award'], $matches)) {
                $row['class'] = strtolower($matches[1]);
            } else {
                $row['class'] = '';
            }
            if ($row['rank'] === null) {
                $honorable[] = $row['team'];
            } elseif (in_array($row['award'], ['Ranked', 'Highest Honors', 'High Honors', 'Honors'], true)) {
                $ranked[$row['award']][] = $row;
            } else {
                $awarded[] = $row;
            }
        }

        usort($regionWinners, fn($a, $b) => $a['group'] <=> $b['group']);

        $collator = new Collator('en_US');
        $collator->sort($honorable);
        foreach ($ranked as &$rankedTeams) {
            usort($rankedTeams, function (array $a, array $b) use ($collator): int {
                if ($a['rank'] !== $b['rank']) {
                    return $a['rank'] <=> $b['rank'];
                }

                return $collator->compare($a['team'], $b['team']);
            });
        }
        unset($rankedTeams);

        $problems     = $scoreboard->getProblems();
        $matrix       = $scoreboard->getMatrix();
        $firstToSolve = [];

        foreach ($problems as $problem) {
            $firstToSolve[$problem->getProbid()] = [
                'problem' => $problem->getShortname(),
                'problem_name' => $problem->getProblem()->getName(),
                'team' => null,
                'time' => null,
                'rank' => null,
            ];
            foreach ($teams as $team) {
                if (!isset($categories[$team->getCategory()->getCategoryid()]) || $team->getCategory()->getSortorder() !== $sortOrder) {
                    continue;
                }

                $matrixItem = $matrix[$team->getTeamid()][$problem->getProbid()];
                if ($matrixItem->isCorrect && $scoreboard->solvedFirst($team, $problem)) {
                    $firstToSolve[$problem->getProbid()] = [
                        'problem' => $problem->getShortname(),
                        'problem_name' => $problem->getProblem()->getName(),
                        'team' => $teamNames[$team->getIcpcId()],
                        'rank' => $rankPerTeam[$team->getIcpcId()] ?: '-',
                        'time' => Utils::scoretime($matrixItem->time, $scoreIsInSeconds),
                    ];
                }
            }
        }

        usort($firstToSolve, function ($a, $b) {
            if ($a['time'] === null) {
                $a['time'] = PHP_INT_MAX;
            }
            if ($b['time'] === null) {
                $b['time'] = PHP_INT_MAX;
            }
            if ($a['time'] === $b['time']) {
                return $a['problem'] <=> $b['problem'];
            }
            return $a['time'] <=> $b['time'];
        });

        $data = [
            'awarded' => $awarded,
            'ranked' => $ranked,
            'honorable' => $honorable,
            'regionWinners' => $regionWinners,
            'firstToSolve' => $firstToSolve,
            'domjudgeVersion' => $this->domjudgeVersion,
            'title' => sprintf('Results for %s', $contest->getName()),
            'sortOrder' => $sortOrder,
        ];

        return $this->twig->render('jury/export/results.html.twig', $data);
    }

    protected function getClarificationsHtml(): Response
    {
        $contest = $this->dj->getCurrentContest();
        if ($contest === null) {
            throw new BadRequestHttpException('No current contest');
        }

        $queues              = (array)$this->config->get('clar_queues');
        $clarificationQueues = [null => 'Unassigned issues'];
        foreach ($queues as $key => $val) {
            $clarificationQueues[$key] = $val;
        }

        $categories = (array)$this->config->get('clar_categories');

        $clarificationCategories = [];
        foreach ($categories as $key => $val) {
            $clarificationCategories[$key] = $val;
        }

        /** @var Clarification[] $clarifications */
        $clarifications = $this->em->createQueryBuilder()
            ->from(Clarification::class, 'c')
            ->leftJoin('c.problem', 'p')
            ->select('c')
            ->andWhere('c.contest = :contest')
            ->setParameter('contest', $contest)
            ->addOrderBy('c.category')
            ->addOrderBy('p.probid')
            ->addOrderBy('c.submittime')
            ->addOrderBy('c.clarid')
            ->getQuery()
            ->getResult();

        $grouped = [];

        foreach ($clarifications as $clarification) {
            $queue = $clarification->getQueue();

            if (!$clarification->getInReplyTo()) {
                if (!isset($grouped[$queue])) {
                    $grouped[$queue] = [];
                }
                $grouped[$queue][$clarification->getClarid()] = $clarification;
            }
        }

        /** @var ContestProblem[] $contestProblems */
        $contestProblems = $this->em->createQueryBuilder()
            ->from(ContestProblem::class, 'cp')
            ->select('cp')
            ->andWhere('cp.contest = :contest')
            ->setParameter('contest', $contest)
            ->getQuery()
            ->getResult();
        $contestProblemsIndexed = [];
        foreach ($contestProblems as $cp) {
            $contestProblemsIndexed[$cp->getProblem()->getProbid()] = $cp;
        }
        $contestProblems = $contestProblemsIndexed;

        return $this->render('jury/export/clarifications.html.twig', [
            'domjudgeVersion' => $this->domjudgeVersion,
            'title' => sprintf('Clarifications for %s', $contest->getName()),
            'grouped' => $grouped,
            'queues' => $clarificationQueues,
            'categories' => $clarificationCategories,
            'contest' => $contest,
            'problems' => $contestProblems,
        ]);
    }

    /**
     * @param array<string, string[]> $allMessages
     */
    private function postMessages(array $allMessages): void
    {
        foreach (['info', 'warning', 'danger'] as $type) {
            if (!empty($allMessages[$type])) {
                $this->addFlash($type, implode("\n", $allMessages[$type]));
            }
        }
    }
}
