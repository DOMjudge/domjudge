<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Entity\Clarification;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\TeamCategory;
use App\Form\Type\BaylorCmsType;
use App\Form\Type\ContestExportType;
use App\Form\Type\ContestImportType;
use App\Form\Type\JsonImportType;
use App\Form\Type\TsvImportType;
use App\Service\BaylorCmsService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ImportExportService;
use App\Service\ScoreboardService;
use App\Utils\Scoreboard\Filter;
use App\Utils\Scoreboard\ScoreboardMatrixItem;
use App\Utils\Utils;
use Collator;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Yaml\Yaml;

/**
 * @Route("/jury/import-export")
 * @IsGranted("ROLE_ADMIN")
 */
class ImportExportController extends BaseController
{
    /**
     * @var BaylorCmsService
     */
    protected $baylorCmsService;

    /**
     * @var ImportExportService
     */
    protected $importExportService;

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var ScoreboardService
     */
    protected $scoreboardService;

    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var ConfigurationService
     */
    protected $config;

    /**
     * @var EventLogService
     */
    protected $eventLogService;

    /** @var string */
    protected $domjudgeVersion;

    /**
     * ImportExportController constructor.
     *
     * @param BaylorCmsService       $baylorCmsService
     * @param ImportExportService    $importExportService
     * @param EntityManagerInterface $em
     * @param ScoreboardService      $scoreboardService
     * @param DOMJudgeService        $dj
     * @param ConfigurationService   $config
     * @param EventLogService        $eventLogService
     * @param string                 $domjudgeVersion
     */
    public function __construct(
        BaylorCmsService $baylorCmsService,
        ImportExportService $importExportService,
        EntityManagerInterface $em,
        ScoreboardService $scoreboardService,
        DOMJudgeService $dj,
        ConfigurationService $config,
        EventLogService $eventLogService,
        string $domjudgeVersion
    ) {
        $this->baylorCmsService    = $baylorCmsService;
        $this->importExportService = $importExportService;
        $this->em                  = $em;
        $this->scoreboardService   = $scoreboardService;
        $this->dj                  = $dj;
        $this->config              = $config;
        $this->eventLogService     = $eventLogService;
        $this->domjudgeVersion     = $domjudgeVersion;
    }

    /**
     * @Route("", name="jury_import_export")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function indexAction(Request $request)
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

        $baylorForm = $this->createForm(BaylorCmsType::class);

        $baylorForm->handleRequest($request);

        if ($baylorForm->isSubmitted() && $baylorForm->isValid()) {
            $contestId   = $baylorForm->get('contest_id')->getData();
            $accessToken = $baylorForm->get('access_token')->getData();
            if ($baylorForm->get('fetch_teams')->isClicked()) {
                if ($this->baylorCmsService->importTeams($accessToken, $contestId, $message)) {
                    $this->addFlash('success', 'Teams successfully imported');
                } else {
                    $this->addFlash('danger', $message);
                }
            } else {
                if ($this->baylorCmsService->uploadStandings($accessToken, $contestId, $message)) {
                    $this->addFlash('success', 'Standings successfully uploaded');
                } else {
                    $this->addFlash('danger', $message);
                }
            }
            return $this->redirectToRoute('jury_import_export');
        }

        /** @var TeamCategory[] $teamCategories */
        $teamCategories = $this->em->createQueryBuilder()
            ->from(TeamCategory::class, 'c', 'c.categoryid')
            ->select('c.sortorder')
            ->where('c.visible = 1')
            ->groupBy('c.sortorder')
            ->orderBy('c.sortorder')
            ->getQuery()
            ->getResult();
        $sortOrders     = array_map(function ($teamCategory) {
            return $teamCategory["sortorder"];
        }, $teamCategories);

        return $this->render('jury/import_export.html.twig', [
            'tsv_form' => $tsvForm->createView(),
            'json_form' => $jsonForm->createView(),
            'baylor_form' => $baylorForm->createView(),
            'sort_orders' => $sortOrders,
        ]);
    }

    /**
     * @Route("/contest-yaml", name="jury_import_export_yaml")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function contestYamlAction(Request $request)
    {
        $exportForm = $this->createForm(ContestExportType::class);

        $exportForm->handleRequest($request);

        if ($exportForm->isSubmitted() && $exportForm->isValid()) {
            /** @var Contest $contest */
            $contest  = $exportForm->get('contest')->getData();
            $response = new StreamedResponse();
            $response->setCallback(function () use ($contest) {
                echo Yaml::dump($this->importExportService->getContestYamlData($contest));
            });
            $response->headers->set('Content-Type', 'application/x-yaml');
            $response->headers->set('Content-Disposition', 'attachment; filename="contest.yaml"');
            $response->headers->set('Content-Transfer-Encoding', 'binary');
            $response->headers->set('Connection', 'Keep-Alive');
            $response->headers->set('Accept-Ranges', 'bytes');

            return $response;
        }

        $importForm = $this->createForm(ContestImportType::class);

        $importForm->handleRequest($request);

        if ($importForm->isSubmitted() && $importForm->isValid()) {
            /** @var UploadedFile $file */
            $file = $importForm->get('file')->getData();
            $data = Yaml::parseFile($file->getRealPath(), Yaml::PARSE_DATETIME);
            if ($this->importExportService->importContestYaml($data, $message, $cid)) {
                $this->addFlash('success',
                                sprintf('The file %s is successfully imported.', $file->getClientOriginalName()));
            } else {
                $this->addFlash('danger', $message);
            }
            return $this->redirectToRoute('jury_import_export_yaml');
        }

        return $this->render('jury/import_export_contest_yaml.html.twig', [
            'export_form' => $exportForm->createView(),
            'import_form' => $importForm->createView(),
        ]);
    }

    /**
     * @Route("/export/{type<groups|teams|scoreboard|results>}.tsv", name="jury_tsv_export")
     * @param Request $request
     * @param string  $type
     * @return RedirectResponse|StreamedResponse
     * @throws \Exception
     */
    public function exportTsvAction(Request $request, string $type)
    {
        $data    = [];
        $version = 1;
        try {
            switch ($type) {
                case 'groups':
                    $data = $this->importExportService->getGroupData();
                    break;
                case 'teams':
                    $data = $this->importExportService->getTeamData();
                    break;
                case 'scoreboard':
                    $data = $this->importExportService->getScoreboardData();
                    break;
                case 'results':
                    $sortOrder = $request->query->getInt('sort_order');
                    $data      = $this->importExportService->getResultsData($sortOrder);
                    break;
            }
        } catch (BadRequestHttpException $e) {
            $this->addFlash('danger', $e->getMessage());
            return $this->redirectToRoute('jury_import_export');
        }

        $response = new StreamedResponse();
        $response->setCallback(function () use ($type, $version, $data) {
            echo sprintf("%s\t%s\n", $type, $version);
            // output the rows, escaping any reserved characters in the data
            foreach ($data as $row) {
                echo implode("\t", array_map(function ($field) {
                    return Utils::toTsvField((string)$field);
                }, $row)) . "\n";
            }
        });
        $filename = sprintf('%s.tsv', $type);
        $response->headers->set('Content-Type', sprintf('text/plain; name="%s"; charset=utf-8', $filename));
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));

        return $response;
    }

    /**
     * @Route("/export/{type<results|results-icpc|clarifications>}.html", name="jury_html_export")
     * @param Request $request
     * @param string  $type
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function exportHtmlAction(Request $request, string $type)
    {
        try {
            switch ($type) {
                case 'results':
                case 'results-icpc':
                    return $this->getResultsHtml($request, $type === 'results-icpc');
                case 'clarifications':
                    return $this->getClarificationsHtml();
            }
        } catch (BadRequestHttpException $e) {
            $this->addFlash('danger', $e->getMessage());
            return $this->redirectToRoute('jury_import_export');
        }
    }

    /**
     * Get the results HTML
     * @param Request $request
     * @param bool    $useIcpcLayout
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    protected function getResultsHtml(Request $request, bool $useIcpcLayout)
    {
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
            $teamNames[$team->getExternalid() ?? $team->getTeamid()] = $team->getEffectiveName();
        }

        $awarded       = [];
        $ranked        = [];
        $honorable     = [];
        $regionWinners = [];

        $sortOrder = $request->query->getInt('sort_order');

        foreach ($this->importExportService->getResultsData($sortOrder) as $row) {
            $team = $teamNames[$row[0]];

            if ($row[6] !== '') {
                $regionWinners[] = [
                    'group' => $row[6],
                    'team' => $team,
                ];
            }

            $row = [
                'team' => $team,
                'rank' => $row[1],
                'award' => $row[2],
                'solved' => $row[3],
                'total_time' => $row[4],
                'max_time' => $row[5],
            ];
            if (preg_match('/^(.*) Medal$/', $row['award'], $matches)) {
                $row['class'] = strtolower($matches[1]);
            } else {
                $row['class'] = '';
            }
            if ($row['rank'] === '') {
                $honorable[] = $row['team'];
            } elseif ($row['award'] === 'Ranked') {
                $ranked[] = $row;
            } else {
                $awarded[] = $row;
            }
        }

        usort($regionWinners, function ($a, $b) {
            return $a['group'] <=> $b['group'];
        });

        $collator = new Collator('en_US');
        $collator->sort($honorable);

        $problems     = $scoreboard->getProblems();
        $matrix       = $scoreboard->getMatrix();
        $firstToSolve = [];

        foreach ($problems as $problem) {
            $firstToSolve[$problem->getProbid()] = [
                'problem' => $problem->getShortname(),
                'problem_name' => $problem->getProblem()->getName(),
                'team' => null,
                'time' => null,
            ];
            foreach ($teams as $team) {
                if (!isset($categories[$team->getCategoryid()]) || $team->getCategory()->getSortorder() !== $sortOrder) {
                    continue;
                }

                /** @var ScoreboardMatrixItem $matrixItem */
                $matrixItem = $matrix[$team->getTeamid()][$problem->getProbid()];
                if ($matrixItem->isCorrect && $scoreboard->solvedFirst($team, $problem)) {
                    $firstToSolve[$problem->getProbid()] = [
                        'problem' => $problem->getShortname(),
                        'problem_name' => $problem->getProblem()->getName(),
                        'team' => $teamNames[$team->getApiId($this->eventLogService)],
                        'time' => Utils::scoretime($matrixItem->getTime(), $scoreIsInSeconds),
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
            'download' => $request->query->getBoolean('download'),
            'sortOrder' => $sortOrder,
        ];
        if ($useIcpcLayout) {
            $response = $this->render('jury/export/results_icpc.html.twig', $data);
        } else {
            $response = $this->render('jury/export/results.html.twig', $data);
        }

        if ($request->query->getBoolean('download')) {
            $response->headers->set('Content-disposition', 'attachment; filename=results.html');
        }

        return $response;
    }

    /**
     * Get the clarifications HTML
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    protected function getClarificationsHtml()
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
            ->select('c')
            ->andWhere('c.contest = :contest')
            ->setParameter(':contest', $contest)
            ->addOrderBy('c.category')
            ->addOrderBy('c.probid')
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
            ->setParameter(':contest', $contest)
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
}
