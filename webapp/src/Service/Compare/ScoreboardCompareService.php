<?php declare(strict_types=1);

namespace App\Service\Compare;

use App\DataTransferObject\Scoreboard\Problem;
use App\DataTransferObject\Scoreboard\Scoreboard;

/**
 * @extends AbstractCompareService<Scoreboard>
 */
class ScoreboardCompareService extends AbstractCompareService
{
    protected function parseFile(string $file)
    {
        return $this->serializer->deserialize(file_get_contents($file), Scoreboard::class, 'json', ['disable_type_enforcement' => true]);
    }

    public function compare($object1, $object2): void
    {
        if ($object1->eventId !== $object2->eventId) {
            $this->addMessage(MessageType::INFO, 'Event ID does not match', $object1->eventId, $object2->eventId);
        }

        if ($object1->time !== $object2->time) {
            $this->addMessage(MessageType::INFO, 'Time does not match', $object1->time, $object2->time);
        }

        if ($object1->contestTime !== $object2->contestTime) {
            $this->addMessage(MessageType::INFO, 'Contest time does not match', $object1->contestTime, $object2->contestTime);
        }

        if (($object1->state->started ?? '') !== ($object2->state->started ?? '')) {
            $this->addMessage(MessageType::WARNING, 'State started does not match', $object1->state->started, $object2->state->started);
        }

        if (($object1->state->ended ?? '') !== ($object2->state->ended ?? '')) {
            $this->addMessage(MessageType::WARNING, 'State ended does not match', $object1->state->ended, $object2->state->ended);
        }

        if (($object1->state->frozen ?? '') !== ($object2->state->frozen ?? '')) {
            $this->addMessage(MessageType::WARNING, 'State frozen does not match', $object1->state->frozen, $object2->state->frozen);
        }

        if (($object1->state->thawed ?? '') !== ($object2->state->thawed ?? '')) {
            $this->addMessage(MessageType::WARNING, 'State thawed does not match', $object1->state->thawed, $object2->state->thawed);
        }

        if (($object1->state->finalized ?? '') !== ($object2->state->finalized ?? '')) {
            $this->addMessage(MessageType::WARNING, 'State finalized does not match', $object1->state->finalized, $object2->state->finalized);
        }

        if (($object1->state->endOfUpdates ?? '') !== ($object2->state->endOfUpdates ?? '')) {
            $this->addMessage(MessageType::WARNING, 'State end of updates does not match', $object1->state->endOfUpdates, $object2->state->endOfUpdates);
        }

        if (count($object1->rows) !== count($object2->rows)) {
            $this->addMessage(MessageType::ERROR, 'Number of rows does not match', (string)count($object1->rows), (string)count($object2->rows));
        }

        foreach ($object1->rows as $index => $row) {
            if ($row->teamId !== $object2->rows[$index]->teamId) {
                $this->addMessage(MessageType::ERROR, sprintf('Row %d: team ID does not match', $index), $row->teamId, $object2->rows[$index]->teamId);
            }

            if ($row->rank !== $object2->rows[$index]->rank) {
                $this->addMessage(MessageType::ERROR, sprintf('Row %d: rank does not match', $index), (string)$row->rank, (string)$object2->rows[$index]->rank);
            }

            if ($row->score->numSolved !== $object2->rows[$index]->score->numSolved) {
                $this->addMessage(MessageType::ERROR, sprintf('Row %d: num solved does not match', $index), (string)$row->score->numSolved, (string)$object2->rows[$index]->score->numSolved);
            }

            if ($row->score->totalTime !== $object2->rows[$index]->score->totalTime) {
                $this->addMessage(MessageType::ERROR, sprintf('Row %d: total time does not match', $index), (string)$row->score->totalTime, (string)$object2->rows[$index]->score->totalTime);
            }

            foreach ($row->problems as $problem) {
                /** @var Problem|null $problemForSecond */
                $problemForSecond = null;

                foreach ($object2->rows[$index]->problems as $problem2) {
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
                    $this->addMessage(MessageType::ERROR, sprintf('Row %d: Problem %s solved in first file, but not found in second file', $index, $problem->problemId));
                } elseif ($problemForSecond !== null && $problem->solved !== $problemForSecond->solved) {
                    $this->addMessage(MessageType::ERROR, sprintf('Row %d: Problem %s solved does not match', $index, $problem->problemId), (string)$problem->solved, (string)$problemForSecond->solved);
                }

                if ($problemForSecond) {
                    if ($problem->numJudged !== $problemForSecond->numJudged) {
                        $this->addMessage(MessageType::ERROR, sprintf('Row %d: Problem %s num judged does not match', $index, $problem->problemId), (string)$problem->numJudged, (string)$problemForSecond->numJudged);
                    }

                    if ($problem->numPending !== $problemForSecond->numPending) {
                        $this->addMessage(MessageType::ERROR, sprintf('Row %d: Problem %s num pending does not match', $index, $problem->problemId), (string)$problem->numPending, (string)$problemForSecond->numPending);
                    }

                    if ($problem->time !== $problemForSecond->time) {
                        // This is an info message for now, since PC^2 doesn't expose time info
                        $this->addMessage(MessageType::INFO, sprintf('Row %d: Problem %s time does not match', $index, $problem->problemId), (string)$problem->time, (string)$problemForSecond->time);
                    }
                }
            }

            foreach ($object2->rows[$index]->problems as $problem2) {
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
                    $this->addMessage(MessageType::ERROR, sprintf('Row %d: Problem %s solved in second file, but not found in first file', $index, $problem2->problemId));
                }
            }
        }
    }
}
