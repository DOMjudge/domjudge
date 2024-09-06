<?php declare(strict_types=1);

namespace App\DataTransferObject;

class ResultRow
{
    public function __construct(
        public readonly string $teamId,
        public readonly ?int $rank,
        public readonly string $award,
        public readonly int $numSolved,
        public readonly int $totalTime,
        public readonly int $timeOfLastSubmission,
        public readonly ?string $groupWinner = null,
    ) {}

    /**
     * @return array{
     *     team_id: string,
     *     rank: int|null,
     *     award: string,
     *     num_solved: int,
     *     total_time: int,
     *     time_of_last_submission: int,
     *     group_winner: string|null,
     * }
     */
    public function toArray(): array
    {
        return [
            'team_id' => $this->teamId,
            'rank' => $this->rank,
            'award' => $this->award,
            'num_solved' => $this->numSolved,
            'total_time' => $this->totalTime,
            'time_of_last_submission' => $this->timeOfLastSubmission,
            'group_winner' => $this->groupWinner,
        ];
    }
}
