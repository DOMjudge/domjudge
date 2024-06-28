<?php declare(strict_types=1);

namespace App\Command;

use App\DataTransferObject\Scoreboard\Scoreboard;
use App\Service\Compare\ScoreboardCompareService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @extends AbstractCompareCommand<Scoreboard>
 */
#[AsCommand(
    name: 'compare:scoreboard',
    description: 'Compare scoreboard between two files'
)]
class CompareScoreboardCommand extends AbstractCompareCommand
{
    public function __construct(
        SerializerInterface $serializer,
        ScoreboardCompareService $compareService
    ) {
        parent::__construct($serializer, $compareService);
    }
}
