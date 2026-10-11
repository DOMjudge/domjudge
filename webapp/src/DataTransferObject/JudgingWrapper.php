<?php declare(strict_types=1);

namespace App\DataTransferObject;

use App\Controller\API\AbstractRestController as ARC;
use App\Entity\AbstractJudgement;
use App\Utils\CcsApiVersion;
use JMS\Serializer\Annotation as Serializer;

readonly class JudgingWrapper
{
    public function __construct(
        #[Serializer\Inline]
        protected AbstractJudgement $judging,
        #[Serializer\SerializedName('judgement_type_id')]
        protected ?string $judgementTypeId = null,
        #[Serializer\Groups([ARC::GROUP_NONSTRICT, CcsApiVersion::Format_2026_01->value])]
        #[Serializer\SerializedName('simplified_judgement_type_id')]
        protected ?string $simplifiedJudgementTypeId = null
    ) {}
}
