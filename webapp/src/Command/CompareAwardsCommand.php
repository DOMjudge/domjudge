<?php declare(strict_types=1);

namespace App\Command;

use App\DataTransferObject\Award;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

#[AsCommand(
    name: 'compare:awards',
    description: 'Compare awards between two files'
)]
class CompareAwardsCommand extends AbstractCompareCommand
{
    protected function compare(
        array &$messages,
        bool &$success,
        string $file1,
        string $file2,
    ): void {
        try {
            /** @var Award[] $awards1 */
            $awards1 = $this->serializer->deserialize(file_get_contents($file1), Award::class . '[]', 'json');
        } catch (ExceptionInterface $e) {
            $this->addMessage($messages, $success, 'error', sprintf('Error deserializing file "%s": %s', $file1, $e->getMessage()));
        }
        try {
            /** @var Award[] $awards2 */
            $awards2 = $this->serializer->deserialize(file_get_contents($file2), Award::class . '[]', 'json');
        } catch (ExceptionInterface $e) {
            $this->addMessage($messages, $success, 'error', sprintf('Error deserializing file "%s": %s', $file2, $e->getMessage()));
        }

        if (!$success || !isset($awards1) || !isset($awards2)) {
            return;
        }

        /** @var array<string,Award> $awards1Indexed */
        $awards1Indexed = [];
        foreach ($awards1 as $award) {
            $awards1Indexed[$award->id] = $award;
        }

        /** @var array<string,Award> $awards2Indexed */
        $awards2Indexed = [];
        foreach ($awards2 as $award) {
            $awards2Indexed[$award->id] = $award;
        }

        foreach ($awards1Indexed as $awardId => $award) {
            if (!isset($awards2Indexed[$awardId])) {
                if (!$award->teamIds) {
                    $this->addMessage($messages, $success, 'info', sprintf('Award "%s" not found in second file, but has no team ID\'s in first file', $awardId));
                } else {
                    $this->addMessage($messages, $success, 'error', sprintf('Award "%s" not found in second file', $awardId));
                }
            } else {
                $award2 = $awards2Indexed[$awardId];
                if ($award->citation !== $award2->citation) {
                    $this->addMessage($messages, $success, 'warning', sprintf('Award "%s" has different citation', $awardId), $award->citation, $award2->citation);
                }
                $award1TeamIds = $award->teamIds;
                sort($award1TeamIds);
                $award2TeamIds = $award2->teamIds;
                sort($award2TeamIds);
                if ($award1TeamIds !== $award2TeamIds) {
                    $this->addMessage($messages, $success, 'error', sprintf('Award "%s" has different team ID\'s', $awardId), implode(', ', $award->teamIds), implode(', ', $award2->teamIds));
                }
            }
        }

        foreach ($awards2Indexed as $awardId => $award) {
            if (!isset($awards1Indexed[$awardId])) {
                if (!$award->teamIds) {
                    $this->addMessage($messages, $success, 'info', sprintf('Award "%s" not found in first file, but has no team ID\'s in second file', $awardId));
                } else {
                    $this->addMessage($messages, $success, 'error', sprintf('Award "%s" not found in first file', $awardId));
                }
            }
        }
    }
}
