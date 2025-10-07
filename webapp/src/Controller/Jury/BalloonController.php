<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use App\Service\BalloonService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\RedirectResponse;
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

    /**
     * @param array<string, array<string>> $filters
     * @param int[] $defaultCategories
     */
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

    /**
     * @return array{}|array{
     *     refresh: array{after: int, url: string, ajax: bool},
     *     isfrozen: bool,
     *     hasFilters: bool,
     *     filteredAffiliations: list<TeamAffiliation>,
     *     filteredLocations: list<Team>,
     *     filteredCategories: list<TeamCategory>,
     *     availableCategories: list<TeamCategory>,
     *     defaultCategories: list<int>,
     *     balloons: list<mixed>
     * }
     */
    #[Route(path: '', name: 'jury_balloons')]
    #[Template(template: 'jury/balloons.html.twig')]
    public function indexAction(BalloonService $balloonService): array
    {
        $contest = $this->dj->getCurrentContest();
        if (is_null($contest)) {
            return [];
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
        $filters              = Utils::jsonDecode((string)$this->dj->getCookie('domjudge_balloonsfilter') ?: '[]');
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
                ->from(Team::class, 'a', 'a.location')
                ->select('a')
                ->where('a.location IN (:locations)')
                ->setParameter('locations', $filters['location-str'])
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

        return [
            'refresh' => [
                'after' => 60,
                'url' => $this->generateUrl('jury_balloons'),
                'ajax' => true
            ],
            'isfrozen' => isset($contest->getState()->frozen),
            'hasFilters' => !$this->areDefault($filters, $defaultCategories),
            'filteredAffiliations' => $filteredAffiliations,
            'filteredLocations' => $filteredLocations,
            'filteredCategories' => $filteredCategories,
            'availableCategories' => $availableCategories,
            'defaultCategories' => $defaultCategories,
            'balloons' => $balloons_table
        ];
    }

    #[Route(path: '/{balloonId}/done', name: 'jury_balloons_setdone')]
    public function setDoneAction(int $balloonId, BalloonService $balloonService): RedirectResponse
    {
        $balloonService->setDone($balloonId);

        return $this->redirectToRoute("jury_balloons");
    }
}
