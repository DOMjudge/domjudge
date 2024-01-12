<?php declare(strict_types=1);

namespace App\DataTransferObject;

use JMS\Serializer\Annotation as Serializer;

class ExtendedContestStatus
{
    public function __construct(
        public string $cid,
        #[Serializer\Inline()]
        public ContestStatus $status,
    ) {}
}
