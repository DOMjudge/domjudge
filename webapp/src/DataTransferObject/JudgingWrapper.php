<?php declare(strict_types=1);

namespace App\DataTransferObject;

use App\Entity\AbstractJudgement;
use JMS\Serializer\Annotation as Serializer;

readonly class JudgingWrapper
{
    public function __construct(
        #[Serializer\Inline]
        protected AbstractJudgement $judging,
        #[Serializer\SerializedName('judgement_type_id')]
        protected ?string $judgementTypeId = null
    ) {}
}
