<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Service\BalloonService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jury/balloons")
 * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_BALLOON')")
 */
class BalloonController extends AbstractController
{
    protected EntityManagerInterface $em;
    protected DOMJudgeService $dj;
    protected ConfigurationService $config;
    protected EventLogService $eventLogService;

    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        ConfigurationService $config,
        EventLogService $eventLogService
    ) {
        $this->em              = $em;
        $this->dj              = $dj;
        $this->config          = $config;
        $this->eventLogService = $eventLogService;
    }

    /**
     * @Route("", name="jury_balloons")
     */
    public function indexAction(BalloonService $balloonService): Response
    {
        $contest = $this->dj->getCurrentContest();
        if (is_null($contest)) {
            return $this->render('jury/balloons.html.twig');
        }

        $balloons_table = $balloonService->collectBalloonTable($contest);

        // Add CSS class and actions.
        foreach ($balloons_table as $element) {
            if ($element['data']['done']) {
                $cssclass = 'disabled';
                $balloonactions = [[]];
            } else {
                $cssclass = null;
                $balloonactions = [[
                    'icon' => 'running',
                    'title' => 'mark balloon as done',
                    'link' => $this->generateUrl('jury_balloons_setdone', [
                        'balloonId' => $element['data']['balloonid'],
                    ])]];
            }
            $element['data']['actions'] = $balloonactions;
            $element['data']['cssclass'] = $cssclass;
        }

        // Load preselected filters
        $filters              = $this->dj->jsonDecode((string)$this->dj->getCookie('domjudge_balloonsfilter') ?: '[]');
        $filteredAffiliations = [];
        $filteredLocations    = [];
        if (isset($filters['affiliation-id'])) {
            /** @var TeamAffiliation[] $filteredAffiliations */
            $filteredAffiliations = $this->em->createQueryBuilder()
                ->from(TeamAffiliation::class, 'a')
                ->select('a')
                ->where('a.affilid IN (:affilIds)')
                ->setParameter('affilIds', $filters['affiliation-id'])
                ->getQuery()
                ->getResult();
        }
        if (isset($filters['location-str'])) {
            /** @var Team[] $filteredLocations */
            $filteredLocations = $this->em->createQueryBuilder()
                ->from(Team::class, 'a')
                ->select('a')
                ->where('a.room IN (:rooms)')
                ->setParameter('rooms', $filters['location-str'])
                ->getQuery()
                ->getResult();
        }

        return $this->render('jury/balloons.html.twig', [
            'refresh' => [
                'after' => 60,
                'url' => $this->generateUrl('jury_balloons'),
                'ajax' => true
            ],
            'isfrozen' => isset($contest->getState()['frozen']),
            'hasFilters' => !empty($filters),
            'filteredAffiliations' => $filteredAffiliations,
            'filteredLocations' => $filteredLocations,
            'balloons' => $balloons_table
        ]);
    }

    /**
     * @Route("/{balloonId}/done", name="jury_balloons_setdone")
     */
    public function setDoneAction(int $balloonId, BalloonService $balloonService): RedirectResponse
    {
        $balloonService->setDone($balloonId);

        return $this->redirectToRoute("jury_balloons");
    }
}
