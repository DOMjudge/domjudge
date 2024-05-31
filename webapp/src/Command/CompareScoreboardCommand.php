<?php declare(strict_types=1);

namespace App\Command;

use App\DataTransferObject\Scoreboard\Problem;
use App\DataTransferObject\Scoreboard\Scoreboard;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

#[AsCommand(
    name: 'compare:scoreboard',
    description: 'Compare scoreboard between two files'
)]
class CompareScoreboardCommand extends AbstractCompareCommand
{
    protected function compare(
        array &$messages,
        bool &$success,
        string $file1,
        string $file2,
    ): void {
        try {
            /** @var Scoreboard $scoreboard1 */
            $scoreboard1 = $this->serializer->deserialize(file_get_contents($file1), Scoreboard::class, 'json', ['disable_type_enforcement' => true]);
        } catch (ExceptionInterface $e) {
            $this->addMessage($messages, $success, 'error', sprintf('Error deserializing file "%s": %s', $file1, $e->getMessage()));
        }
        try {
            /** @var Scoreboard $scoreboard2 */
            $scoreboard2 = $this->serializer->deserialize(file_get_contents($file2), Scoreboard::class, 'json', ['disable_type_enforcement' => true]);
        } catch (ExceptionInterface $e) {
            $this->addMessage($messages, $success, 'error', sprintf('Error deserializing file "%s": %s', $file2, $e->getMessage()));
        }

        if (!$success || !isset($scoreboard1) || !isset($scoreboard2)) {
            return;
        }

        if ($scoreboard1->eventId !== $scoreboard2->eventId) {
            $this->addMessage($messages, $success, 'info', 'Event ID does not match', $scoreboard1->eventId, $scoreboard2->eventId);
        }

        if ($scoreboard1->time !== $scoreboard2->time) {
            $this->addMessage($messages, $success, 'info', 'Time does not match', $scoreboard1->time, $scoreboard2->time);
        }

        if ($scoreboard1->contestTime !== $scoreboard2->contestTime) {
            $this->addMessage($messages, $success, 'info', 'Contest time does not match', $scoreboard1->contestTime, $scoreboard2->contestTime);
        }

        if (($scoreboard1->state->started ?? '') !== ($scoreboard2->state->started ?? '')) {
            $this->addMessage($messages, $success, 'warning', 'State started does not match', $scoreboard1->state->started, $scoreboard2->state->started);
        }

        if (($scoreboard1->state->ended ?? '') !== ($scoreboard2->state->ended ?? '')) {
            $this->addMessage($messages, $success, 'warning', 'State ended does not match', $scoreboard1->state->ended, $scoreboard2->state->ended);
        }

        if (($scoreboard1->state->frozen ?? '') !== ($scoreboard2->state->frozen ?? '')) {
            $this->addMessage($messages, $success, 'warning', 'State frozen does not match', $scoreboard1->state->frozen, $scoreboard2->state->frozen);
        }

        if (($scoreboard1->state->thawed ?? '') !== ($scoreboard2->state->thawed ?? '')) {
            $this->addMessage($messages, $success, 'warning', 'State thawed does not match', $scoreboard1->state->thawed, $scoreboard2->state->thawed);
        }

        if (($scoreboard1->state->finalized ?? '') !== ($scoreboard2->state->finalized ?? '')) {
            $this->addMessage($messages, $success, 'warning', 'State finalized does not match', $scoreboard1->state->finalized, $scoreboard2->state->finalized);
        }

        if (($scoreboard1->state->endOfUpdates ?? '') !== ($scoreboard2->state->endOfUpdates ?? '')) {
            $this->addMessage($messages, $success, 'warning', 'State end of updates does not match', $scoreboard1->state->endOfUpdates, $scoreboard2->state->endOfUpdates);
        }

        if (count($scoreboard1->rows) !== count($scoreboard2->rows)) {
            $this->addMessage($messages, $success, 'error', 'Number of rows does not match', (string)count($scoreboard1->rows), (string)count($scoreboard2->rows));
        }

        foreach ($scoreboard1->rows as $index => $row) {
            if ($row->teamId !== $scoreboard2->rows[$index]->teamId) {
                $this->addMessage($messages, $success, 'error', sprintf('Row %d: Team ID does not match', $index), $row->teamId, $scoreboard2->rows[$index]->teamId);
            }

            if ($row->rank !== $scoreboard2->rows[$index]->rank) {
                $this->addMessage($messages, $success, 'error', sprintf('Row %d: Rank does not match', $index), (string)$row->rank, (string)$scoreboard2->rows[$index]->rank);
            }

            if ($row->score->numSolved !== $scoreboard2->rows[$index]->score->numSolved) {
                $this->addMessage($messages, $success, 'error', sprintf('Row %d: Num solved does not match', $index), (string)$row->score->numSolved, (string)$scoreboard2->rows[$index]->score->numSolved);
            }

            if ($row->score->totalTime !== $scoreboard2->rows[$index]->score->totalTime) {
                $this->addMessage($messages, $success, 'error', sprintf('Row %d: Total time does not match', $index), (string)$row->score->totalTime, (string)$scoreboard2->rows[$index]->score->totalTime);
            }

            // Problem messages are mostly info for now, since PC^2 doesn't expose time info
            foreach ($row->problems as $problem) {
                /** @var Problem|null $problemForSecond */
                $problemForSecond = null;

                foreach ($scoreboard2->rows[$index]->problems as $problem2) {
                    // PC^2 uses different problem ID's. For now also match on `Id = {problemId}-{digits}`
                    if ($problem->problemId === $problem2->problemId) {
                        $problemForSecond = $problem2;
                        break;
                    } elseif (preg_match('/^Id = ' . preg_quote($problem->problemId, '/') . '-+\d+$/', $problem2->problemId)) {
                        $problemForSecond = $problem2;
                        break;
                    }
                }

                if ($problemForSecond === null && $problem->solved) {
                    $this->addMessage($messages, $success, 'error', sprintf('Row %d: Problem %s solved in first file, but not in second file', $index, $problem->problemId));
                } elseif ($problemForSecond !== null && $problem->solved !== $problemForSecond->solved) {
                    $this->addMessage($messages, $success, 'error', sprintf('Row %d: Problem %s solved does not match', $index, $problem->problemId), (string)$problem->solved, (string)$problemForSecond->solved);
                }

                if ($problemForSecond) {
                    if ($problem->numJudged !== $problemForSecond->numJudged) {
                        $this->addMessage($messages, $success, 'error', sprintf('Row %d: Problem %s num judged does not match', $index, $problem->problemId), (string)$problem->numJudged, (string)$problemForSecond->numJudged);
                    }

                    if ($problem->numPending !== $problemForSecond->numPending) {
                        $this->addMessage($messages, $success, 'error', sprintf('Row %d: Problem %s num pending does not match', $index, $problem->problemId), (string)$problem->numPending, (string)$problemForSecond->numPending);
                    }

                    if ($problem->time !== $problemForSecond->time) {
                        $this->addMessage($messages, $success, 'info', sprintf('Row %d: Problem %s time does not match', $index, $problem->problemId), (string)$problem->time, (string)$problemForSecond->time);
                    }
                }
            }

            foreach ($scoreboard2->rows[$index]->problems as $problem2) {
                $problemForFirst = null;

                foreach ($row->problems as $problem) {
                    // PC^2 uses different problem ID's. For now also match on `Id = {problemId}-{digits}`
                    if ($problem->problemId === $problem2->problemId) {
                        $problemForFirst = $problem;
                        break;
                    } elseif (preg_match('/^Id = ' . preg_quote($problem->problemId, '/') . '-+\d+$/', $problem2->problemId)) {
                        $problemForFirst = $problem;
                        break;
                    }
                }

                if ($problemForFirst === null && $problem2->solved) {
                    $this->addMessage($messages, $success, 'error', sprintf('Row %d: Problem %s solved in second file, but not in first file', $index, $problem2->problemId));
                }
            }
        }
    }
}
