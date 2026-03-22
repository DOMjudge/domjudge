<?php declare(strict_types=1);

namespace App\Controller;

use App\DataTransferObject\SubmissionRestriction;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\ScoreboardType;
use App\Entity\Submission;
use App\Entity\Team;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

trait ScoreboardSubmissionsTrait
{
    protected function getSubmissionsPageResponse(Contest $contest, string $teamId, string $problemId, string $dataRouteName, string $baseTemplate = 'public/base.html.twig'): Response
    {
        /** @var Team|null $team */
        $team = $this->em->getRepository(Team::class)->findByExternalId($teamId);
        if ($team && $team->getScoringCategory() && !$team->getScoringCategory()->getVisible()) {
            $team = null;
        }

        if (!$team) {
            throw $this->createNotFoundException('Team not found.');
        }

        /** @var ContestProblem|null $problem */
        $problem = $this->em->createQueryBuilder()
            ->from(ContestProblem::class, 'cp')
            ->select('cp')
            ->innerJoin('cp.problem', 'p')
            ->andWhere('cp.contest = :contest')
            ->andWhere('p.externalid = :problem')
            ->setParameter('contest', $contest)
            ->setParameter('problem', $problemId)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$problem) {
            throw $this->createNotFoundException('Problem not found');
        }

        return $this->render('public/team_submissions.html.twig', [
            'contest' => $contest,
            'problem' => $problem,
            'team' => $team,
            'submissions_data_route' => $dataRouteName,
            'base_template' => $baseTemplate,
        ]);
    }

    protected function getSubmissionsDataResponse(Contest $contest, ?string $teamId, ?string $problemId): JsonResponse
    {
        $scoreboard = $this->scoreboardService->getScoreboard($contest);

        if ($scoreboard === null) {
            throw $this->createNotFoundException('No submission data found');
        }

        if (!$this->config->get('show_teams_submissions')) {
            throw $this->createNotFoundException('Submissions are not visible');
        }

        $teamIds = array_map(fn(Team $team) => $team->getTeamid(), $scoreboard->getTeamsInDescendingOrder());

        /** @var Submission[] $submissions */
        $submissions = $this->submissionService->getSubmissionList(
            [$contest->getCid() => $contest],
            restrictions: new SubmissionRestriction(
                teamIds: $teamIds,
                valid: true,
            ),
            paginated: false
        )[0];

        $submissionData = [];

        // We prepend IDs with team- and problem- to prevent PHP from casting
        // numeric string keys to integers, which could cause json_encode to
        // output a JSON array instead of an object
        foreach ($scoreboard->getTeamsInDescendingOrder() as $team) {
            if ($teamId && $teamId !== $team->getExternalid()) {
                continue;
            }
            $teamKey = 'team-' . $team->getExternalid();
            $submissionData[$teamKey] = [];
            foreach ($scoreboard->getProblems() as $problem) {
                if ($problemId && $problemId !== $problem->getExternalId()) {
                    continue;
                }
                $problemKey = 'problem-' . $problem->getExternalId();
                $submissionData[$teamKey][$problemKey] = [];
            }
        }

        $verificationRequired = (bool)$this->config->get('verification_required');

        foreach ($submissions as $submission) {
            $teamKey = 'team-' . $submission->getTeam()->getExternalid();
            $problemKey = 'problem-' . $submission->getProblem()->getExternalid();
            if ($teamId && $teamId !== $submission->getTeam()->getExternalid()) {
                continue;
            }
            if ($problemId && $problemId !== $submission->getProblem()->getExternalid()) {
                continue;
            }
            $item = [
                'time' => $this->twigExtension->printtime($submission->getSubmittime(), contest: $contest),
                'language' => $submission->getLanguage()->getName(),
                'verdict' => $this->submissionVerdict($submission, $contest, $verificationRequired),
            ];
            if ($contest->getScoreboardType() === ScoreboardType::SCORE) {
                $item['score'] = $submission->getScore();
            }
            $submissionData[$teamKey][$problemKey][] = $item;
        }

        return new JsonResponse([
            'submissions' => $submissionData,
        ]);
    }

    protected function submissionVerdict(
        Submission $submission,
        Contest $contest,
        bool $verificationRequired
    ): string {
        if ($submission->getSubmittime() >= $contest->getEndtime()) {
            return $this->twigExtension->printResult('too-late');
        }
        if ($contest->getFreezetime() && $submission->getSubmittime() >= $contest->getFreezetime() && !$contest->getFreezeData()->showFinal()) {
            return $this->twigExtension->printResult('');
        }
        if ($contest->isExternalSourceUseJudgements()) {
            $judging = $submission->getValidExternalJudgement();
        } else {
            $judging = $submission->getValidJudging();
        }
        if (!$judging || !$judging->getResult()) {
            return $this->twigExtension->printResult('');
        }
        if ($verificationRequired && !$judging->getVerified()) {
            return $this->twigExtension->printResult('');
        }
        return $this->twigExtension->printResult($judging->getResult(), onlyRejectedForIncorrect: true);
    }
}
