<?php declare(strict_types=1);

namespace App\Service\Compare;

use App\DataTransferObject\Award;

/**
 * @extends AbstractCompareService<Award[]>
 */
class AwardCompareService extends AbstractCompareService
{
    protected function parseFile(string $file)
    {
        return $this->serializer->deserialize(file_get_contents($file), Award::class . '[]', 'json');
    }

    public function compare($object1, $object2): void
    {
        $awards1Indexed = [];
        foreach ($object1 as $award) {
            $awards1Indexed[$award->id] = $award;
        }

        $awards2Indexed = [];
        foreach ($object2 as $award) {
            $awards2Indexed[$award->id] = $award;
        }

        foreach ($awards1Indexed as $awardId => $award) {
            if (!isset($awards2Indexed[$awardId])) {
                if (!$award->teamIds) {
                    $this->addMessage(MessageType::INFO, sprintf('Award "%s" not found in second file, but has no team ID\'s in first file', $awardId));
                } else {
                    $this->addMessage(MessageType::ERROR, sprintf('Award "%s" not found in second file', $awardId));
                }
            } else {
                $award2 = $awards2Indexed[$awardId];
                if ($award->citation !== $award2->citation) {
                    $this->addMessage(MessageType::WARNING, sprintf('Award "%s" has different citation', $awardId), $award->citation, $award2->citation);
                }
                $award1TeamIds = $award->teamIds;
                sort($award1TeamIds);
                $award2TeamIds = $award2->teamIds;
                sort($award2TeamIds);
                if ($award1TeamIds !== $award2TeamIds) {
                    $this->addMessage(MessageType::ERROR, sprintf('Award "%s" has different team ID\'s', $awardId), implode(', ', $award->teamIds), implode(', ', $award2->teamIds));
                }
            }
        }

        foreach ($awards2Indexed as $awardId => $award) {
            if (!isset($awards1Indexed[$awardId])) {
                if (!$award->teamIds) {
                    $this->addMessage(MessageType::INFO, sprintf('Award "%s" not found in first file, but has no team ID\'s in second file', $awardId));
                } else {
                    $this->addMessage(MessageType::ERROR, sprintf('Award "%s" not found in first file', $awardId));
                }
            }
        }
    }
}
