<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Clarification;
use App\Entity\Contest;

readonly class ClarificationService
{
    public function __construct(
        protected ConfigurationService $config,
    ) {}

    /**
     * @return array<string, string>
     */
    public function getClarificationCategories(): array
    {
        return $this->config->get('clar_categories');
    }

    /**
     * @return string[]
     */
    public function getClarificationDefaultAnswers(): array
    {
        return $this->config->get('clar_answers');
    }

    public function getClarificationDefaultProblemQueue(): string
    {
        return $this->config->get('clar_default_problem_queue');
    }

    /**
     * @return array<string, string>
     */
    public function getClarificationQueues(): array
    {
        return $this->config->get('clar_queues');
    }

    public function getClarificationMaximumBodyLength(): int
    {
        return $this->config->get('clar_max_body_length');
    }
}
