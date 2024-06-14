<?php declare(strict_types=1);

namespace App\Service\Compare;

use App\DataTransferObject\Result;
use Symfony\Component\Serializer\Encoder\CsvEncoder;

/**
 * @extends AbstractCompareService<Result[]>
 */
class ResultsCompareService extends AbstractCompareService
{
    protected function parseFile(string $file)
    {
        $resultsContents = file_get_contents($file);
        if (!str_starts_with($resultsContents, "results\t1")) {
            $this->addMessage(MessageType::ERROR, sprintf("File \"%s\" does not start with \"results\t1\"", $file));
            return null;
        }

        $resultsContents = substr($resultsContents, strpos($resultsContents, "\n") + 1);

        // Prefix file with a fake header, so we can deserialize them
        $resultsContents = "team_id\trank\taward\tnum_solved\ttotal_time\tlast_time\tgroup_winner\n" . $resultsContents;

        $results = $this->serializer->deserialize($resultsContents, Result::class . '[]', 'csv', [
            CsvEncoder::DELIMITER_KEY => "\t",
        ]);

        // Sort results: first by num_solved, then by total_time
        usort($results, fn(
            Result $a,
            Result $b
        ) => $a->numSolved === $b->numSolved ? $a->totalTime <=> $b->totalTime : $b->numSolved <=> $a->numSolved);

        return $results;
    }

    public function compare($object1, $object2): void
    {
        /** @var array<string,Result> $results1Indexed */
        $results1Indexed = [];
        foreach ($object1 as $result) {
            $results1Indexed[$result->teamId] = $result;
        }

        /** @var array<string,Result> $results2Indexed */
        $results2Indexed = [];
        foreach ($object2 as $result) {
            $results2Indexed[$result->teamId] = $result;
        }

        foreach ($object1 as $result) {
            if (!isset($results2Indexed[$result->teamId])) {
                $this->addMessage(MessageType::ERROR, sprintf('Team "%s" not found in second file', $result->teamId));
            } else {
                $result2 = $results2Indexed[$result->teamId];
                if ($result->rank !== $result2->rank) {
                    $this->addMessage(MessageType::ERROR, sprintf('Team %s has different rank', $result->teamId), (string)$result->rank, (string)$result2->rank);
                }
                if ($result->award !== $result2->award) {
                    $this->addMessage(MessageType::ERROR, sprintf('Team %s has different award', $result->teamId), $result->award, $result2->award);
                }
                if ($result->numSolved !== $result2->numSolved) {
                    $this->addMessage(MessageType::ERROR, sprintf('Team %s has different num_solved', $result->teamId), (string)$result->numSolved, (string)$result2->numSolved);
                }
                if ($result->totalTime !== $result2->totalTime) {
                    $this->addMessage(MessageType::ERROR, sprintf('Team %s has different total_time', $result->teamId), (string)$result->totalTime, (string)$result2->totalTime);
                }
                if ($result->lastTime !== $result2->lastTime) {
                    $this->addMessage(MessageType::ERROR, sprintf('Team %s has different last_time', $result->teamId), (string)$result->lastTime, (string)$result2->lastTime);
                }
                if ($result->groupWinner !== $result2->groupWinner) {
                    $this->addMessage(MessageType::WARNING, sprintf('Team %s has different group_winner', $result->teamId), (string)$result->groupWinner, (string)$result2->groupWinner);
                }
            }
        }

        foreach ($object2 as $result) {
            if (!isset($results1Indexed[$result->teamId])) {
                $this->addMessage(MessageType::ERROR, sprintf('Team %s not found in first file', $result->teamId));
            }
        }
    }
}
