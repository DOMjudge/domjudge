<?php declare(strict_types=1);

namespace App\Controller\API;

trait SerializeJudgeTasksTrait
{
    /**
     * @param JudgeTask[] $judgeTasks
     * @return JudgeTask[]
     * @throws Exception
     */
    private function serializeJudgeTasks(array $judgeTasks, Judgehost $judgehost): array
    {
        if (empty($judgeTasks)) {
            return [];
        }

        // Filter by submit_id.
        $submit_id = $judgeTasks[0]->getSubmission()?->getSubmitid();
        $judgetaskids = [];
        foreach ($judgeTasks as $judgeTask) {
            if ($judgeTask->getType() == 'judging_run') {
                if ($judgeTask->getSubmission()?->getSubmitid() == $submit_id) {
                    $judgetaskids[] = $judgeTask->getJudgetaskid();
                }
            } else {
                // Just pick everything assigned to the judgehost itself or unassigned for now
                $assignedJudgehost = $judgeTask->getJudgehost();
                if ($assignedJudgehost === $judgehost || $assignedJudgehost === null) {
                    $judgetaskids[] = $judgeTask->getJudgetaskid();
                }
            }
        }

        $now = Utils::now();
        $numUpdated = $this->em->getConnection()->executeStatement(
            'UPDATE judgetask SET judgehostid = :judgehostid, starttime = :starttime WHERE starttime IS NULL AND valid = 1 AND judgetaskid IN (:ids)',
            [
                'judgehostid' => $judgehost->getJudgehostid(),
                'starttime' => $now,
                'ids' => $judgetaskids,
            ],
            [
                'ids' => ArrayParameterType::INTEGER,
            ]
        );

        if ($numUpdated == 0) {
            // Bad luck, some other judgehost beat us to it.
            return [];
        }

        // We got at least one, let's update the starttime of the corresponding judging if haven't done so in the past.
        $starttime_set = $this->em->getConnection()->executeStatement(
            'UPDATE judging SET starttime = :starttime WHERE judgingid = :jobid AND starttime IS NULL',
            [
                'starttime' => $now,
                'jobid' => $judgeTasks[0]->getJobId(),
            ]
        );

        if ($starttime_set && $judgeTasks[0]->getType() == JudgeTaskType::JUDGING_RUN) {
            /** @var Submission $submission */
            $submission = $this->em->getRepository(Submission::class)->findOneBy(['submitid' => $submit_id]);
            $teamid = $submission->getTeam()->getTeamid();

            $this->em->getConnection()->executeStatement(
                'UPDATE team SET judging_last_started = :starttime WHERE teamid = :teamid',
                [
                    'starttime' => $now,
                    'teamid' => $teamid,
                ]
            );
        }

        if ($numUpdated == sizeof($judgeTasks)) {
            // We got everything, let's ship it!
            return $judgeTasks;
        }

        // A bit unlucky, we only got partially the assigned work, so query what was assigned to us.
        $queryBuilder = $this->em->createQueryBuilder();
        $partialJudgeTaskIds = array_column(
            $queryBuilder
                ->from(JudgeTask::class, 'jt')
                ->select('jt.judgetaskid')
                ->andWhere('jt.judgehost = :judgehost')
                ->setParameter('judgehost', $judgehost)
                ->andWhere($queryBuilder->expr()->In('jt.judgetaskid', $judgetaskids))
                ->getQuery()
                ->getArrayResult(),
            'judgetaskid');

        $partialJudgeTasks = [];
        foreach ($judgeTasks as $judgeTask) {
            if (in_array($judgeTask->getJudgetaskid(), $partialJudgeTaskIds)) {
                $partialJudgeTasks[] = $judgeTask;
            }
        }
        return $partialJudgeTasks;
    }

}
