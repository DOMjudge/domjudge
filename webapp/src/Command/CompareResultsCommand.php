<?php declare(strict_types=1);

namespace App\Command;

use App\DataTransferObject\Result;
use App\Service\Compare\ResultsCompareService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @extends AbstractCompareCommand<Result[]>
 */
#[AsCommand(
    name: 'compare:results',
    description: 'Compare results between two files'
)]
class CompareResultsCommand extends AbstractCompareCommand
{
    public function __construct(
        SerializerInterface $serializer,
        ResultsCompareService $compareService
    ) {
        parent::__construct($serializer, $compareService);
    }
}
