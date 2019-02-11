<?php declare(strict_types=1);

namespace DOMJudgeBundle\Service;

use Collator;
use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Entity\Team;
use DOMJudgeBundle\Entity\TeamCategory;
use DOMJudgeBundle\Utils\Scoreboard\Filter;
use DOMJudgeBundle\Utils\Scoreboard\ScoreboardMatrixItem;
use DOMJudgeBundle\Utils\Utils;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ImportExportService
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var ScoreboardService
     */
    protected $scoreboardService;

    /**
     * @var DOMJudgeService
     */
    protected $DOMJudgeService;

    public function __construct(
        EntityManagerInterface $entityManager,
        ScoreboardService $scoreboardService,
        DOMJudgeService $DOMJudgeService
    ) {
        $this->entityManager     = $entityManager;
        $this->scoreboardService = $scoreboardService;
        $this->DOMJudgeService   = $DOMJudgeService;
    }

    /**
     * Get group data
     * @return array
     */
    public function getGroupData(): array
    {
        /** @var TeamCategory[] $categories */
        $categories = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:TeamCategory', 'c')
            ->select('c')
            ->where('c.visible = 1')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($categories as $category) {
            $data[] = [$category->getCategoryid(), $category->getName()];
        }

        return $data;
    }

    /**
     * Get team data
     * @return array
     */
    public function getTeamData(): array
    {
        /** @var Team[] $teams */
        $teams = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Team', 't')
            ->join('t.category', 'c')
            ->select('t')
            ->where('c.visible = 1')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($teams as $team) {
            $data[] = [
                $team->getTeamid(),
                $team->getExternalid(),
                $team->getCategoryid(),
                $team->getName(),
                $team->getAffiliation()->getName(),
                $team->getAffiliation()->getShortname(),
                $team->getAffiliation()->getCountry(),
                $team->getAffiliation()->getExternalid(),
            ];
        }

        return $data;
    }

    /**
     * Get scoreboard data
     * @return array
     * @throws \Exception
     */
    public function getScoreboardData(): array
    {
        // We'll here assume that the requested file will be of the current contest,
        // as all our scoreboard interfaces do. Row format explanation:
        // Row	Description	Example content	Type
        // 1	Institution name	University of Virginia	string
        // 2	External ID	24314	integer
        // 3	Position in contest	1	integer
        // 4	Number of problems the team has solved	4	integer
        // 5	Total Time	534	integer
        // 6	Time of the last accepted submission	233	integer   -1 if none
        // 6+2i-1	Number of submissions for problem i	2	integer
        // 6+2i	Time when problem i was solved	233	integer   -1 if not solved

        $contest = $this->DOMJudgeService->getCurrentContest();
        if ($contest === null) {
            throw new BadRequestHttpException('No current contest');
        }
        $scoreIsInSeconds = (bool)$this->DOMJudgeService->dbconfig_get('score_in_seconds', false);
        $scoreboard       = $this->scoreboardService->getScoreboard($contest, true);

        $data = [];
        foreach ($scoreboard->getScores() as $teamScore) {
            $maxtime = -1;
            $drow    = [];
            /** @var ScoreboardMatrixItem $matrixItem */
            foreach ($scoreboard->getMatrix()[$teamScore->getTeam()->getTeamid()] as $matrixItem) {
                $time    = Utils::scoretime($matrixItem->getTime(), $scoreIsInSeconds);
                $drow[]  = $matrixItem->getNumberOfSubmissions();
                $drow[]  = $matrixItem->isCorrect() ? $time : -1;
                $maxtime = max($maxtime, $time);
            }

            $data[] = array_merge(
                [
                    $teamScore->getTeam()->getAffiliation() ? $teamScore->getTeam()->getAffiliation()->getName() : '',
                    $teamScore->getTeam()->getExternalid(),
                    $teamScore->getRank(),
                    $teamScore->getNumberOfPoints(),
                    $teamScore->getTotalTime(),
                    $maxtime,
                ],
                $drow
            );
        }

        return $data;
    }

    /**
     * Get results data
     * @return array
     * @throws \Exception
     */
    public function getResultsData()
    {
        // we'll here assume that the requested file will be of the current contest,
        // as all our scoreboard interfaces do
        // 1 	External ID 	24314 	integer
        // 2 	Rank in contest 	1 	integer
        // 3 	Award 	Gold Medal 	string
        // 4 	Number of problems the team has solved 	4 	integer
        // 5 	Total Time 	534 	integer
        // 6 	Time of the last submission 	233 	integer
        // 7 	Group Winner 	North American 	string

        $contest = $this->DOMJudgeService->getCurrentContest();
        if ($contest === null) {
            throw new BadRequestHttpException('No current contest');
        }


        /** @var TeamCategory[] $categories */
        $categories  = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:TeamCategory', 'c', 'c.categoryid')
            ->select('c')
            ->where('c.visible = 1')
            ->getQuery()
            ->getResult();
        $categoryIds = [];
        foreach ($categories as $category) {
            $categoryIds[] = $category->getCategoryid();
        }

        $scoreIsInSeconds = (bool)$this->DOMJudgeService->dbconfig_get('score_in_seconds', false);
        $filter           = new Filter();
        $filter->setCategories($categoryIds);
        $scoreboard = $this->scoreboardService->getScoreboard($contest, true, $filter);

        /** @var Team[] $teams */
        $teams = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Team', 't', 't.externalid')
            ->select('t')
            ->where('t.externalid IS NOT NULL')
            ->orderBy('t.externalid')
            ->getQuery()
            ->getResult();

        $numberOfTeams = count($scoreboard->getScores());
        // determine number of problems solved by median team
        $count  = 0;
        $median = 0;
        foreach ($scoreboard->getScores() as $teamScore) {
            $count++;
            $median = $teamScore->getNumberOfPoints();
            if ($count > $numberOfTeams / 2) {
                break;
            }
        }

        $ranks        = [];
        $groupWinners = [];
        $data         = [];

        foreach ($scoreboard->getScores() as $teamScore) {
            $maxTime = -1;
            /** @var ScoreboardMatrixItem $matrixItem */
            foreach ($scoreboard->getMatrix()[$teamScore->getTeam()->getTeamid()] as $matrixItem) {
                $time    = Utils::scoretime($matrixItem->getTime(), $scoreIsInSeconds);
                $maxTime = max($maxTime, $time);
            }

            $rank           = $teamScore->getRank();
            $numberOfPoints = $teamScore->getNumberOfPoints();
            if ($rank <= 4) {
                $awardString = 'Gold Medal';
            } elseif ($rank <= 8) {
                $awardString = 'Silver Medal';
            } elseif ($rank <= 12 + $contest->getB()) {
                $awardString = 'Bronze Medal';
            } elseif ($numberOfPoints >= $median) {
                // teams with equally solved number of problems get the same rank
                if (!isset($ranks[$numberOfPoints])) {
                    $ranks[$numberOfPoints] = $rank;
                }
                $rank        = $ranks[$numberOfPoints];
                $awardString = 'Ranked';
            } else {
                $awardString = 'Honorable';
                $rank        = '';
            }

            $groupWinner = "";
            $categoryId  = $teamScore->getTeam()->getCategoryid();
            if (!isset($groupWinners[$categoryId])) {
                $groupWinners[$categoryId] = true;
                $groupWinner               = $teamScore->getTeam()->getCategory()->getName();
            }

            $data[] = [
                $teamScore->getTeam()->getExternalid(),
                $rank,
                $awardString,
                $teamScore->getNumberOfPoints(),
                $teamScore->getTotalTime(),
                $maxTime,
                $groupWinner
            ];
        }

        // sort by rank/name
        uasort($data, function ($a, $b) use ($teams) {
            if ($a[1] != $b[1]) {
                // Honorable mention has no rank
                if ($a[1] === '') {
                    return 1;
                } elseif ($b[1] === '') {
                    return -11;
                }
                return $a[1] - $b[1];
            }
            $teamA = $teams[$a[0]] ?? null;
            $teamB = $teams[$b[0]] ?? null;
            if ($teamA) {
                $nameA = $teamA->getName();
            } else {
                $nameA = '';
            }
            if ($teamB) {
                $nameB = $teamB->getName();
            } else {
                $nameB = '';
            }
            $collator = new Collator('en');
            return $collator->compare($nameA, $nameB);
        });

        return $data;
    }
}
