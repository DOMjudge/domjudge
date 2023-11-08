<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\JudgeTask;
use App\Entity\Judging;
use App\Entity\JudgingRun;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\Submission;
use App\Entity\SubmissionFile;
use App\Entity\Team;
use App\Entity\User;
use App\Utils\FreezeData;
use App\Utils\Utils;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\Join;
use InvalidArgumentException;
use PHPUnit\Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use ZipArchive;

/**
 * Class SubmissionService
 * @package App\Service
 */
class PointsScoreService
{
    protected EntityManagerInterface $em;
    protected LoggerInterface $logger;
    protected DOMJudgeService $dj;
    protected ConfigurationService $config;
    protected EventLogService $eventLogService;
    protected ScoreboardService $scoreboardService;

    public function __construct(
        EntityManagerInterface $em,
        LoggerInterface        $logger,
        DOMJudgeService        $dj,
        ConfigurationService   $config,
        EventLogService        $eventLogService,
        ScoreboardService      $scoreboardService
    )
    {
        $this->em = $em;
        $this->logger = $logger;
        $this->dj = $dj;
        $this->config = $config;
        $this->eventLogService = $eventLogService;
        $this->scoreboardService = $scoreboardService;
    }

    /**
     * @param JudgingRun[] $judgingRuns
     */
    public static function getScoredPoints(Judging         $judging,
                                           array           $judgingRuns,
                                           ContestProblem  $contestProblem,
                                           DOMJudgeService $dj): float
    {
        if ($judging->getResult() === 'correct') {
            return $contestProblem->getPoints();
        }

        $pointsScored = 0;

        foreach ($contestProblem->getProblem()->getTestcaseGroups() as $testcaseGroup) {
            foreach ($judgingRuns as $judgingRun) {
                $runGroup = $judgingRun->getTestcase()->getTestcaseGroup();

                if ($runGroup === null) {
                    continue;
                }

                if ($runGroup->getTestcasegroupid() === $testcaseGroup->getTestcasegroupid()
                    && $judgingRun->getRunresult() !== "correct") {
                    continue (2);
                }
            }

            $pointsScored += $testcaseGroup->getPointsPercentage() * $contestProblem->getPoints();
        }

        return $pointsScored;
    }
}
