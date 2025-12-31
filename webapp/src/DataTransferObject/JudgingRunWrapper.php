<?php declare(strict_types=1);

namespace App\DataTransferObject;

use App\Entity\AbstractRun;
use JMS\Serializer\Annotation as Serializer;

readonly class JudgingRunWrapper
{
    public function __construct(
        #[Serializer\Inline]
        protected AbstractRun $judgingRun,
        #[Serializer\SerializedName('judgement_type_id')]
        protected ?string $judgementTypeId = null
    ) {}
}
