<?php declare(strict_types=1);

namespace App\Command;

use App\DataTransferObject\Result;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

#[AsCommand(
    name: 'compare:results',
    description: 'Compare results between two files'
)]
class CompareResultsCommand extends AbstractCompareCommand
{

    protected function compare(
        array &$messages,
        bool &$success,
        string $file1,
        string $file2,
    ): void {
        $results1Contents = file_get_contents($file1);
        if (!str_starts_with($results1Contents, "results\t1")) {
            $this->addMessage($messages, $success, 'error', sprintf("File \"%s\" does not start with \"results\t1\"", $file1));
        }
        $results2Contents = file_get_contents($file2);
        if (!str_starts_with($results2Contents, "results\t1")) {
            $this->addMessage($messages, $success, 'error', sprintf("File \"%s\" does not start with \"results\t1\"", $file2));
        }

        if (!$success) {
            return;
        }

        $results1Contents = substr($results1Contents, strpos($results1Contents, "\n") + 1);
        $results2Contents = substr($results2Contents, strpos($results2Contents, "\n") + 1);

        // Prefix both files with a fake header, so we can deserialize them
        $results1Contents = "team_id\trank\taward\tnum_solved\ttotal_time\tlast_time\tgroup_winner\n" . $results1Contents;
        $results2Contents = "team_id\trank\taward\tnum_solved\ttotal_time\tlast_time\tgroup_winner\n" . $results2Contents;

        try {
            /** @var Result[] $results1 */
            $results1 = $this->serializer->deserialize($results1Contents, Result::class . '[]', 'csv', [
                CsvEncoder::DELIMITER_KEY => "\t",
            ]);
        } catch (ExceptionInterface $e) {
            $this->addMessage($messages, $success, 'error', sprintf('Error deserializing file "%s": %s', $file1, $e->getMessage()));
        }

        try {
            /** @var Result[] $results2 */
            $results2 = $this->serializer->deserialize($results2Contents, Result::class . '[]', 'csv', [
                CsvEncoder::DELIMITER_KEY => "\t",
            ]);
        } catch (ExceptionInterface $e) {
            $this->addMessage($messages, $success, 'error', sprintf('Error deserializing file "%s": %s', $file2, $e->getMessage()));
        }

        if (!$success || !isset($results1) || !isset($results2)) {
            return;
        }

        // Sort results for both files: first by num_solved, then by total_time
        usort($results1, fn(
            Result $a,
            Result $b
        ) => $a->numSolved === $b->numSolved ? $a->totalTime <=> $b->totalTime : $b->numSolved <=> $a->numSolved);
        usort($results2, fn(
            Result $a,
            Result $b
        ) => $a->numSolved === $b->numSolved ? $a->totalTime <=> $b->totalTime : $b->numSolved <=> $a->numSolved);

        /** @var array<string,Result> $results1Indexed */
        $results1Indexed = [];
        foreach ($results1 as $result) {
            $results1Indexed[$result->teamId] = $result;
        }

        /** @var array<string,Result> $results2Indexed */
        $results2Indexed = [];
        foreach ($results2 as $result) {
            $results2Indexed[$result->teamId] = $result;
        }

        foreach ($results1 as $result) {
            if (!isset($results2Indexed[$result->teamId])) {
                $this->addMessage($messages, $success, 'error', sprintf('Team "%s" not found in second file', $result->teamId));
            } else {
                $result2 = $results2Indexed[$result->teamId];
                if ($result->rank !== $result2->rank) {
                    $this->addMessage($messages, $success, 'error', sprintf('Team %s has different rank', $result->teamId), (string)$result->rank, (string)$result2->rank);
                }
                if ($result->award !== $result2->award) {
                    $this->addMessage($messages, $success, 'error', sprintf('Team %s has different award', $result->teamId), $result->award, $result2->award);
                }
                if ($result->numSolved !== $result2->numSolved) {
                    $this->addMessage($messages, $success, 'error', sprintf('Team %s has different num_solved', $result->teamId), (string)$result->numSolved, (string)$result2->numSolved);
                }
                if ($result->totalTime !== $result2->totalTime) {
                    $this->addMessage($messages, $success, 'error', sprintf('Team %s has different total_time', $result->teamId), (string)$result->totalTime, (string)$result2->totalTime);
                }
                if ($result->lastTime !== $result2->lastTime) {
                    $this->addMessage($messages, $success, 'error', sprintf('Team %s has different last_time', $result->teamId), (string)$result->lastTime, (string)$result2->lastTime);
                }
                if ($result->groupWinner !== $result2->groupWinner) {
                    $this->addMessage($messages, $success, 'warning', sprintf('Team %s has different group_winner', $result->teamId), (string)$result->groupWinner, (string)$result2->groupWinner);
                }
            }
        }

        foreach ($results2 as $result) {
            if (!isset($results1Indexed[$result->teamId])) {
                $this->addMessage($messages, $success, 'error', sprintf('Team %s not found in first file', $result->teamId));
            }
        }
    }
}
