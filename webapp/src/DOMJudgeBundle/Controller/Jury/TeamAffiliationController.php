<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Jury;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Controller\BaseController;
use DOMJudgeBundle\Entity\TeamAffiliation;
use DOMJudgeBundle\Form\Type\TeamAffiliationType;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\EventLogService;
use DOMJudgeBundle\Service\ScoreboardService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jury/affiliations")
 * @Security("has_role('ROLE_JURY')")
 */
class TeamAffiliationController extends BaseController
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var KernelInterface
     */
    protected $kernel;

    /**
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * TeamCategoryController constructor.
     * @param EntityManagerInterface $em
     * @param DOMJudgeService        $dj
     * @param KernelInterface        $kernel
     * @param EventLogService        $eventLogService
     */
    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        KernelInterface $kernel,
        EventLogService $eventLogService
    ) {
        $this->em              = $em;
        $this->dj              = $dj;
        $this->kernel          = $kernel;
        $this->eventLogService = $eventLogService;
    }

    /**
     * @Route("", name="jury_team_affiliations")
     */
    public function indexAction(Request $request, Packages $assetPackage, KernelInterface $kernel)
    {
        $em               = $this->em;
        $teamAffiliations = $em->createQueryBuilder()
            ->select('a', 'COUNT(t.teamid) AS num_teams')
            ->from('DOMJudgeBundle:TeamAffiliation', 'a')
            ->leftJoin('a.teams', 't')
            ->orderBy('a.name', 'ASC')
            ->groupBy('a.affilid')
            ->getQuery()->getResult();

        $showFlags = $this->dj->dbconfig_get('show_flags', true);

        $table_fields = [
            'affilid' => ['title' => 'ID', 'sort' => true],
            'shortname' => ['title' => 'shortname', 'sort' => true],
            'name' => ['title' => 'name', 'sort' => true, 'default_sort' => true],
        ];

        if ($showFlags) {
            $table_fields['country'] = ['title' => 'country', 'sort' => true];
        }

        $table_fields['num_teams'] = ['title' => '# teams', 'sort' => true];

        // Insert external ID field when configured to use it
        if ($externalIdField = $this->eventLogService->externalIdFieldForEntity(TeamAffiliation::class)) {
            $table_fields = array_slice($table_fields, 0, 1, true) +
                [$externalIdField => ['title' => 'external ID', 'sort' => true]] +
                array_slice($table_fields, 1, null, true);
        }

        $webDir = realpath(sprintf('%s/../web', $kernel->getRootDir()));

        $propertyAccessor        = PropertyAccess::createPropertyAccessor();
        $team_affiliations_table = [];
        foreach ($teamAffiliations as $teamAffiliationData) {
            /** @var TeamAffiliation $teamAffiliation */
            $teamAffiliation    = $teamAffiliationData[0];
            $affiliationdata    = [];
            $affiliationactions = [];
            // Get whatever fields we can from the affiliation object itself
            foreach ($table_fields as $k => $v) {
                if ($propertyAccessor->isReadable($teamAffiliation, $k)) {
                    $affiliationdata[$k] = ['value' => $propertyAccessor->getValue($teamAffiliation, $k)];
                }
            }

            if ($this->isGranted('ROLE_ADMIN')) {
                $affiliationactions[] = [
                    'icon' => 'edit',
                    'title' => 'edit this affiliation',
                    'link' => $this->generateUrl('jury_team_affiliation_edit', [
                        'affilId' => $teamAffiliation->getAffilid(),
                    ])
                ];
                $affiliationactions[] = [
                    'icon' => 'trash-alt',
                    'title' => 'delete this affiliation',
                    'link' => $this->generateUrl('jury_team_affiliation_delete', [
                        'affilId' => $teamAffiliation->getAffilid(),
                    ]),
                    'ajaxModal' => true,
                ];
            }

            $affiliationdata['num_teams'] = ['value' => $teamAffiliationData['num_teams']];
            if ($showFlags) {
                $countryCode     = $teamAffiliation->getCountry();
                $countryFlag     = $countryCode;
                $countryFlagPath = sprintf('images/countries/%s.png', $countryCode);
                if (file_exists($webDir . '/' . $countryFlagPath)) {
                    $countryFlag = sprintf('<img src="%s" alt="%s" class="countryflag">',
                                           $assetPackage->getUrl($countryFlagPath), $countryCode);
                }
                $affiliationdata['country'] = [
                    'value' => $countryFlag,
                    'sortvalue' => $countryCode,
                    'title' => $countryCode,
                ];
            }

            $team_affiliations_table[] = [
                'data' => $affiliationdata,
                'actions' => $affiliationactions,
                'link' => $this->generateUrl('jury_team_affiliation', ['affilId' => $teamAffiliation->getAffilid()]),
            ];
        }

        return $this->render('@DOMJudge/jury/team_affiliations.html.twig', [
            'team_affiliations' => $team_affiliations_table,
            'table_fields' => $table_fields,
            'num_actions' => $this->isGranted('ROLE_ADMIN') ? 2 : 0,
        ]);
    }

    /**
     * @Route("/{affilId}", name="jury_team_affiliation", requirements={"affilId": "\d+"})
     * @param Request           $request
     * @param ScoreboardService $scoreboardService
     * @param int               $affilId
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function viewAction(Request $request, ScoreboardService $scoreboardService, int $affilId)
    {
        /** @var TeamAffiliation $teamAffiliation */
        $teamAffiliation = $this->em->getRepository(TeamAffiliation::class)->find($affilId);
        if (!$teamAffiliation) {
            throw new NotFoundHttpException(sprintf('Team affiliation with ID %s not found', $affilId));
        }

        $data = [
            'teamAffiliation' => $teamAffiliation,
            'showFlags' => $this->dj->dbconfig_get('show_flags', true),
            'refresh' => [
                'after' => 30,
                'url' => $this->generateUrl('jury_team_affiliation', ['affilId' => $teamAffiliation->getAffilid()]),
                'ajax' => true,
            ],
            'maxWidth' => $this->dj->dbconfig_get('team_column_width', 0),
        ];

        if ($currentContest = $this->dj->getCurrentContest()) {
            $data['scoreboard']           = $scoreboardService->getScoreboard($currentContest, true);
            $data['showFlags']            = $this->dj->dbconfig_get('show_flags', true);
            $data['showAffiliationLogos'] = $this->dj->dbconfig_get('show_affiliation_logos', false);
            $data['showAffiliations']     = $this->dj->dbconfig_get('show_affiliations', true);
            $data['showPending']          = $this->dj->dbconfig_get('show_pending', false);
            $data['showTeamSubmissions']  = $this->dj->dbconfig_get('show_teams_submissions', true);
            $data['scoreInSeconds']       = $this->dj->dbconfig_get('score_in_seconds', false);
            $data['limitToTeams']         = $teamAffiliation->getTeams();
        }

        // For ajax requests, only return the submission list partial
        if ($request->isXmlHttpRequest()) {
            $data['displayRank'] = true;
            $data['jury']        = true;
            return $this->render('@DOMJudge/partials/scoreboard_table.html.twig', $data);
        }

        return $this->render('@DOMJudge/jury/team_affiliation.html.twig', $data);
    }

    /**
     * @Route("/{affilId}/edit", name="jury_team_affiliation_edit", requirements={"affilId": "\d+"})
     * @Security("has_role('ROLE_ADMIN')")
     * @param Request $request
     * @param int     $affilId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function editAction(Request $request, int $affilId)
    {
        /** @var TeamAffiliation $teamAffiliation */
        $teamAffiliation = $this->em->getRepository(TeamAffiliation::class)->find($affilId);
        if (!$teamAffiliation) {
            throw new NotFoundHttpException(sprintf('Team affiliation with ID %s not found', $affilId));
        }

        $form = $this->createForm(TeamAffiliationType::class, $teamAffiliation);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->saveEntity($this->em, $this->eventLogService, $this->dj, $teamAffiliation,
                              $teamAffiliation->getAffilid(), false);
            return $this->redirect($this->generateUrl('jury_team_affiliation',
                                                      ['affilId' => $teamAffiliation->getAffilid()]));
        }

        return $this->render('@DOMJudge/jury/team_affiliation_edit.html.twig', [
            'teamAffiliation' => $teamAffiliation,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{affilId}/delete", name="jury_team_affiliation_delete", requirements={"affilId": "\d+"})
     * @Security("has_role('ROLE_ADMIN')")
     * @param Request $request
     * @param int     $affilId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function deleteAction(Request $request, int $affilId)
    {
        /** @var TeamAffiliation $teamAffiliation */
        $teamAffiliation = $this->em->getRepository(TeamAffiliation::class)->find($affilId);
        if (!$teamAffiliation) {
            throw new NotFoundHttpException(sprintf('Team affiliation with ID %s not found', $affilId));
        }

        return $this->deleteEntity($request, $this->em, $this->dj, $this->kernel, $teamAffiliation,
                                   $teamAffiliation->getName(), $this->generateUrl('jury_team_affiliations'));
    }

    /**
     * @Route("/add", name="jury_team_affiliation_add")
     * @Security("has_role('ROLE_ADMIN')")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function addAction(Request $request)
    {
        $teamAffiliation = new TeamAffiliation();

        $form = $this->createForm(TeamAffiliationType::class, $teamAffiliation);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($teamAffiliation);
            $this->saveEntity($this->em, $this->eventLogService, $this->dj, $teamAffiliation,
                              $teamAffiliation->getAffilid(), true);
            return $this->redirect($this->generateUrl('jury_team_affiliation',
                                                      ['affilId' => $teamAffiliation->getAffilid()]));
        }

        return $this->render('@DOMJudge/jury/team_affiliation_add.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
