<?php declare(strict_types=1);

namespace App\Tests\Unit\Service\Compare;

use App\DataTransferObject\Award;
use App\Service\Compare\AwardCompareService;
use App\Service\Compare\Message;
use App\Service\Compare\MessageType;
use Generator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

class AwardCompareServiceTest extends KernelTestCase
{
    /**
     * @param Award[]   $awards1
     * @param Award[]   $awards2
     * @param Message[] $expectedMessages
     *
     * @dataProvider provideCompare
     */
    public function testCompare(array $awards1, array $awards2, array $expectedMessages): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $compareService = new AwardCompareService($serializer);

        $compareService->compare($awards1, $awards2);
        $messages = $compareService->getMessages();

        self::assertEquals($expectedMessages, $messages);
    }

    public function provideCompare(): Generator
    {
        yield [[], [], []];
        yield [
            [new Award('award1', null, [])],
            [],
            [new Message(MessageType::INFO, 'Award "award1" not found in second file, but has no team ID\'s in first file', null, null)],
        ];
        yield [
            [],
            [new Award('award2', null, [])],
            [new Message(MessageType::INFO, 'Award "award2" not found in first file, but has no team ID\'s in second file', null, null)],
        ];
        yield [
            [new Award('award3', null, ["1", "2", "3"])],
            [],
            [new Message(MessageType::ERROR, 'Award "award3" not found in second file', null, null)],
        ];
        yield [
            [],
            [new Award('award4', null, ["1", "2", "3"])],
            [new Message(MessageType::ERROR, 'Award "award4" not found in first file', null, null)],
        ];
        yield [
            [new Award('award1', 'citation1', [])],
            [new Award('award1', 'citation2', [])],
            [new Message(MessageType::WARNING, 'Award "award1" has different citation', 'citation1', 'citation2')],
        ];
        yield [
            [new Award('award1', 'citation1', ["1", "2"])],
            [new Award('award1', 'citation1', ["2", "3"])],
            [new Message(MessageType::ERROR, 'Award "award1" has different team ID\'s', '1, 2', '2, 3')],
        ];
    }
}
