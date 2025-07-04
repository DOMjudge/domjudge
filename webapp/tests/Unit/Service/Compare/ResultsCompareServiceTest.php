<?php declare(strict_types=1);

namespace App\Tests\Unit\Service\Compare;

use App\DataTransferObject\ResultRow;
use App\Service\Compare\Message;
use App\Service\Compare\MessageType;
use App\Service\Compare\ResultsCompareService;
use Generator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

class ResultsCompareServiceTest extends KernelTestCase
{
    /**
     * @param ResultRow[]  $results1
     * @param ResultRow[]  $results2
     * @param Message[] $expectedMessages
     *
     * @dataProvider provideCompare
     */
    public function testCompare(array $results1, array $results2, array $expectedMessages): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $compareService = new ResultsCompareService($serializer);

        $compareService->compare($results1, $results2);
        $messages = $compareService->getMessages();

        self::assertEquals($expectedMessages, $messages);
    }

    public function provideCompare(): Generator
    {
        yield [[], [], []];
        yield [
            [new ResultRow('team1', 1, '', 0, 0, 0)],
            [],
            [new Message(MessageType::ERROR, 'Team "team1" not found in second file', null, null)],
        ];
        yield [
            [],
            [new ResultRow('team2', 1, '', 0, 0, 0)],
            [new Message(MessageType::ERROR, 'Team "team2" not found in first file', null, null)],
        ];
        yield [
            [new ResultRow('team3', 1, '', 0, 0, 0)],
            [new ResultRow('team3', 2, '', 0, 0, 0)],
            [new Message(MessageType::ERROR, 'Team "team3" has different rank', '1', '2')],
        ];
        yield [
            [new ResultRow('team4', 1, 'award1', 0, 0, 0)],
            [new ResultRow('team4', 1, 'award2', 0, 0, 0)],
            [new Message(MessageType::ERROR, 'Team "team4" has different award', 'award1', 'award2')],
        ];
        yield [
            [new ResultRow('team5', 1, 'award3', 1, 0, 0)],
            [new ResultRow('team5', 1, 'award3', 2, 0, 0)],
            [new Message(MessageType::ERROR, 'Team "team5" has different num solved', '1', '2')],
        ];
        yield [
            [new ResultRow('team6', 1, 'award4', 1, 100, 0)],
            [new ResultRow('team6', 1, 'award4', 1, 200, 0)],
            [new Message(MessageType::ERROR, 'Team "team6" has different total time', '100', '200')],
        ];
        yield [
            [new ResultRow('team7', 1, 'award4', 1, 100, 10)],
            [new ResultRow('team7', 1, 'award4', 1, 100, 20)],
            [new Message(MessageType::ERROR, 'Team "team7" has different last time', '10', '20')],
        ];
        yield [
            [new ResultRow('team8', 1, 'award4', 1, 100, 10, 'winner1')],
            [new ResultRow('team8', 1, 'award4', 1, 100, 10, 'winner2')],
            [new Message(MessageType::WARNING, 'Team "team8" has different group winner', 'winner1', 'winner2')],
        ];
    }
}
