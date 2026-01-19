<?php declare(strict_types=1);

namespace App\Utils\Scoreboard;

class ScoreboardMatrixItem
{
    public function __construct(
        public bool $isCorrect,
        public bool $isFirst,
        public int $numSubmissions,
        public int $numSubmissionsPending,
        public float|string $time,
        public int $penaltyTime,
        public int $runtime,
        public ?int $numSubmissionsInFreeze = null,
        public string $points = "",
    ) {}

    /**
     * Get the numeric score value.
     */
    public function getScore(): float
    {
        return $this->points !== "" ? (float)$this->points : 0.0;
    }

    /**
     * Check if this item has a positive score (> 0).
     */
    public function hasPositiveScore(): bool
    {
        return $this->getScore() > 0;
    }

    /**
     * Calculate the score ratio relative to the max score.
     * Returns a value between 0.0 and 1.0.
     */
    public function getScoreRatio(float $maxScore): float
    {
        if ($maxScore <= 0) {
            return 0.0;
        }
        return min(1.0, max(0.0, $this->getScore() / $maxScore));
    }

    /**
     * Calculate the gradient background color based on score ratio.
     * Interpolates from very light green (ratio=0) to score_correct green (ratio=1).
     * Returns CSS rgb() string or empty string if not applicable.
     */
    public function getGradientColor(float $maxScore): string
    {
        if ($maxScore <= 0 || !$this->hasPositiveScore()) {
            return '';
        }

        $ratio = $this->getScoreRatio($maxScore);

        // Interpolate from RGB(220, 245, 220) to RGB(96, 231, 96)
        $r = (int)round(220 - 124 * $ratio);
        $g = (int)round(245 - 14 * $ratio);
        $b = (int)round(220 - 124 * $ratio);

        return "background: rgb($r,$g,$b);";
    }

    /**
     * Calculate the percentage position within the problem's score range.
     * Returns a value between 0.0 and 100.0, or null if range is not defined.
     */
    public function getRangePercent(?float $lowerBound, ?float $upperBound): ?float
    {
        if ($upperBound === null || $upperBound <= ($lowerBound ?? 0)) {
            return null;
        }

        $lower = $lowerBound ?? 0.0;
        $score = $this->getScore();
        $percent = (($score - $lower) / ($upperBound - $lower)) * 100;

        return min(100.0, max(0.0, $percent));
    }
}
