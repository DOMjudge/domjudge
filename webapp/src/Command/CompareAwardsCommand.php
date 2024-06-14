<?php declare(strict_types=1);

namespace App\Command;

use App\DataTransferObject\Award;
use App\Service\Compare\AwardCompareService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @extends AbstractCompareCommand<Award[]>
 */
#[AsCommand(
    name: 'compare:awards',
    description: 'Compare awards between two files'
)]
class CompareAwardsCommand extends AbstractCompareCommand
{
    public function __construct(
        SerializerInterface $serializer,
        AwardCompareService $compareService
    ) {
        parent::__construct($serializer, $compareService);
    }
}
