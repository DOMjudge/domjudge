<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Jury;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Entity\TeamAffiliation;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\EventLogService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jury")
 * @Security("has_role('ROLE_JURY')")
 */
class TeamAffiliationController extends Controller
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var DOMJudgeService
     */
    protected $DOMJudgeService;

    /**
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * TeamCategoryController constructor.
     * @param EntityManagerInterface $entityManager
     * @param DOMJudgeService        $DOMJudgeService
     * @param EventLogService        $eventLogService
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $DOMJudgeService,
        EventLogService $eventLogService
    ) {
        $this->entityManager   = $entityManager;
        $this->DOMJudgeService = $DOMJudgeService;
        $this->eventLogService = $eventLogService;
    }

    /**
     * @Route("/affiliations/", name="jury_team_affiliations")
     */
    public function indexAction(Request $request, Packages $assetPackage, KernelInterface $kernel)
    {
        $em               = $this->entityManager;
        $teamAffiliations = $em->createQueryBuilder()
            ->select('a', 'COUNT(t.teamid) AS num_teams')
            ->from('DOMJudgeBundle:TeamAffiliation', 'a')
            ->leftJoin('a.teams', 't')
            ->orderBy('a.name', 'ASC')
            ->groupBy('a.affilid')
            ->getQuery()->getResult();

        $showFlags = $this->DOMJudgeService->dbconfig_get('show_flags', true);

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
                    'link' => $this->generateUrl('legacy.jury_team_affiliation', [
                        'cmd' => 'edit',
                        'id' => $teamAffiliation->getAffilid(),
                        'referrer' => 'affiliations'
                    ])
                ];
                $affiliationactions[] = [
                    'icon' => 'trash-alt',
                    'title' => 'delete this affiliation',
                    'link' => $this->generateUrl('legacy.jury_delete', [
                        'table' => 'team_affiliation',
                        'affilid' => $teamAffiliation->getAffilid(),
                        'referrer' => '',
                        'desc' => $teamAffiliation->getName(),
                    ])
                ];
            }

            $affiliationdata['num_teams'] = ['value' => $teamAffiliationData['num_teams']];
            if ($showFlags) {
                $countryFlag     = '';
                $countryFlagPath = sprintf('/images/countries/%s.png', $teamAffiliation->getCountry());
                if (file_exists($webDir . $countryFlagPath)) {
                    $countryFlag = sprintf('<img src="%s" />', $assetPackage->getUrl($countryFlagPath));
                }
                $affiliationdata['country'] = [
                    'value' => $countryFlag
                ];
            }

            $team_affiliations_table[] = [
                'data' => $affiliationdata,
                'actions' => $affiliationactions,
                'link' => $this->generateUrl('legacy.jury_team_affiliation', ['id' => $teamAffiliation->getAffilid()]),
            ];
        }

        return $this->render('@DOMJudge/jury/team_affiliations.html.twig', [
            'team_affiliations' => $team_affiliations_table,
            'table_fields' => $table_fields,
            'num_actions' => $this->isGranted('ROLE_ADMIN') ? 2 : 0,
        ]);
    }
}
