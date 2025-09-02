<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Entity\JudgeTask;
use App\Entity\Judging;
use App\Entity\QueueTask;
use App\Service\DOMJudgeService;

trait JudgeRemainingTrait
{
    /**
     * @param Judging[] $judgings
     */
    protected function judgeRemainingJudgings(array $judgings): void
    {
        $lazyEval = $this->config->get('lazy_eval_results');
        $inProgress = [];
        $alreadyRequested = [];
        $invalidJudgings = [];
        $numRequested = 0;
        foreach ($judgings as $judging) {
            $judgingId = $judging->getJudgingid();
            if ($judging->getResult() === null && $lazyEval !== DOMJudgeService::EVAL_ANALYST) {
                $inProgress[] = $judgingId;
            } elseif ($judging->getJudgeCompletely()) {
                $alreadyRequested[] = $judgingId;
            } elseif (!$judging->getValid()) {
                $invalidJudgings[] = $judgingId;
            } else {
                $numRequested = $this->em->getConnection()->executeStatement(
                    'UPDATE judgetask SET valid=1'
                    . ' WHERE jobid=:jobid'
                    . ' AND judgehostid IS NULL',
                    [
                        'jobid' => $judgingId,
                    ]
                );
                $judging->setJudgeCompletely(true);

                $submission = $judging->getSubmission();

                $queueTask = new QueueTask();
                $queueTask->setJudging($judging)
                    ->setPriority(JudgeTask::PRIORITY_LOW)
                    ->setTeam($submission->getTeam())
                    ->setTeamPriority((int)$submission->getSubmittime())
                    ->setStartTime(null);
                $this->em->persist($queueTask);
            }
        }
        $this->em->flush();
        if (count($judgings) === 1) {
            if ($inProgress !== []) {
                $this->addFlash('warning', 'Please be patient, this judging is still in progress.');
            }
            if ($alreadyRequested !== []) {
                $this->addFlash('warning', 'This judging was already requested to be judged completely.');
            }
        } else {
            if ($inProgress !== []) {
                $this->addFlash('warning', sprintf('Please be patient, these judgings are still in progress: %s', implode(', ', $inProgress)));
            }
            if ($alreadyRequested !== []) {
                $this->addFlash('warning', sprintf('These judgings were already requested to be judged completely: %s', implode(', ', $alreadyRequested)));
            }
            if ($invalidJudgings !== []) {
                $this->addFlash('warning', sprintf('These judgings were skipped as they were superseded by other judgings: %s', implode(', ', $invalidJudgings)));
            }
        }
        if ($numRequested === 0) {
            $this->addFlash('warning', 'No more remaining runs to be judged.');
        } else {
            $this->addFlash('info', "Requested $numRequested remaining runs to be judged.");
        }
    }

    public function judgeRemaining(int $contestId = -1, string $categoryId = '', string|int $probId = '', string $langId = ''): void
    {
        $query = $this->em->createQueryBuilder()
            ->from(Judging::class, 'j')
            ->select('j')
            ->join('j.submission', 's')
            ->join('s.team', 't')
            ->join('t.category', 'tc')
            ->andWhere('j.valid = true')
            ->andWhere('j.result != :compiler_error')
            ->setParameter('compiler_error', 'compiler-error');
        if ($contestId > -1) {
            $query
                ->andWhere('s.contest = :contestId')
                ->setParameter('contestId', $contestId);
        }
        if ($categoryId > -1) {
            $query
                ->andWhere('tc.categoryid = :categoryId')
                ->setParameter('categoryId', $categoryId);
        }
        if ($probId !== '') {
            $query
                ->andWhere('s.problem = :probId')
                ->setParameter('probId', $probId);
        }
        if ($langId !== '') {
            $query
                ->andWhere('s.language = :langId')
                ->setParameter('langId', $langId);
        }
        $judgings = $query->getQuery()->getResult();
        $this->judgeRemainingJudgings($judgings);
    }
}
