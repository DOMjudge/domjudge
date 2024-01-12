<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use App\Service\BalloonService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(new Expression("is_granted('ROLE_ADMIN') or is_granted('ROLE_BALLOON')"))]
#[Route(path: '/jury/balloons')]
class BalloonController extends AbstractController
{
    public function __construct(
        protected readonly EntityManagerInterface $em,
        protected readonly DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        protected readonly EventLogService $eventLogService
    ) {}

    private function areDefault(array $filters, array $defaultCategories): bool {
        if (isset($filters['affiliation-id'])) {
            return false;
        }
        if (isset($filters['location-str'])) {
            return false;
        }
        if (!isset($filters['category-id'])) {
            return false;
        }
        if ($filters['category-id'] == $defaultCategories) {
            return true;
        }
        return false;
    }

    #[Route(path: '', name: 'jury_balloons')]
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
        $haveFilters          = $this->dj->getCookie('domjudge_balloonsfilter') != null;
        $filteredAffiliations = [];
        $filteredLocations    = [];
        $filteredCategories   = [];
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
                ->from(Team::class, 'a', 'a.room')
                ->select('a')
                ->where('a.room IN (:rooms)')
                ->setParameter('rooms', $filters['location-str'])
                ->getQuery()
                ->getResult();
        }
        if (!$haveFilters) {
            /** @var TeamCategory[] $filteredCategories */
            $filteredCategories = $this->em->createQueryBuilder()
                ->from(TeamCategory::class, 'c')
                ->select('c')
                ->where('c.visible = true')
                ->getQuery()
                ->getResult();
            /** @var TeamCategory[] $availableCategories */
            $availableCategories = $this->em->createQueryBuilder()
                ->from(TeamCategory::class, 'c')
                ->select('c')
                ->where('c.visible = false')
                ->getQuery()
                ->getResult();
        } elseif (isset($filters['category-id'])) {
            /** @var TeamCategory[] $filteredCategories */
            $filteredCategories = $this->em->createQueryBuilder()
                ->from(TeamCategory::class, 'c')
                ->select('c')
                ->where('c.categoryid IN (:categories)')
                ->setParameter('categories', $filters['category-id'])
                ->getQuery()
                ->getResult();
            /** @var TeamCategory[] $availableCategories */
            $availableCategories = $this->em->createQueryBuilder()
                ->from(TeamCategory::class, 'c')
                ->select('c')
                ->where('c.categoryid NOT IN (:categories)')
                ->setParameter('categories', $filters['category-id'])
                ->getQuery()
                ->getResult();
        } else {
            /** @var TeamCategory[] $availableCategories */
            $availableCategories = $this->em->createQueryBuilder()
                ->from(TeamCategory::class, 'c')
                ->select('c')
                ->getQuery()
                ->getResult();
        }
        $defaultCategories = $this->em->createQueryBuilder()
            ->from(TeamCategory::class, 'c')
            ->select('c.categoryid')
            ->where('c.visible = true')
            ->getQuery()
            ->getArrayResult();
        $defaultCategories = array_column($defaultCategories, "categoryid");

        return $this->render('jury/balloons.html.twig', [
            'refresh' => [
                'after' => 60,
                'url' => $this->generateUrl('jury_balloons'),
                'ajax' => true
            ],
            'isfrozen' => isset($contest->getState()['frozen']),
            'hasFilters' => !$this->areDefault($filters, $defaultCategories),
            'filteredAffiliations' => $filteredAffiliations,
            'filteredLocations' => $filteredLocations,
            'filteredCategories' => $filteredCategories,
            'availableCategories' => $availableCategories,
            'defaultCategories' => $defaultCategories,
            'balloons' => $balloons_table
        ]);
    }

    #[Route(path: '/{balloonId}/done', name: 'jury_balloons_setdone')]
    public function setDoneAction(int $balloonId, BalloonService $balloonService): RedirectResponse
    {
        $balloonService->setDone($balloonId);

        return $this->redirectToRoute("jury_balloons");
    }
}
