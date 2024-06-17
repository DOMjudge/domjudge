<?php declare(strict_types=1);

namespace App\Tests\Unit\Service\Compare;

use App\DataTransferObject\ContestState;
use App\DataTransferObject\Scoreboard\Problem;
use App\DataTransferObject\Scoreboard\Row;
use App\DataTransferObject\Scoreboard\Score;
use App\DataTransferObject\Scoreboard\Scoreboard;
use App\Service\Compare\Message;
use App\Service\Compare\MessageType;
use App\Service\Compare\ScoreboardCompareService;
use Generator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

class ScoreboardCompareServiceTest extends KernelTestCase
{
    /**
     * @param Message[] $expectedMessages
     *
     * @dataProvider provideCompare
     */
    public function testCompare(
        Scoreboard $scoreboard1,
        Scoreboard $scoreboard2,
        array $expectedMessages
    ): void {
        $serializer = $this->createMock(SerializerInterface::class);
        $compareService = new ScoreboardCompareService($serializer);

        $compareService->compare($scoreboard1, $scoreboard2);
        $messages = $compareService->getMessages();

        self::assertEquals($expectedMessages, $messages);
    }

    public function provideCompare(): Generator
    {
        yield [new Scoreboard(), new Scoreboard(), []];
        yield [
            new Scoreboard('123'),
            new Scoreboard('456'),
            [new Message(MessageType::INFO, 'Event ID does not match', '123', '456')],
        ];
        yield [
            new Scoreboard('123', '456'),
            new Scoreboard('123', '123'),
            [new Message(MessageType::INFO, 'Time does not match', '456', '123')],
        ];
        yield [
            new Scoreboard('123', '456', '111'),
            new Scoreboard('123', '456', '222'),
            [new Message(MessageType::INFO, 'Contest time does not match', '111', '222')],
        ];
        yield [
            new Scoreboard('123', '456', '111', new ContestState(started: '123')),
            new Scoreboard('123', '456', '111', new ContestState(started: '456')),
            [new Message(MessageType::WARNING, 'State started does not match', '123', '456')],
        ];
        yield [
            new Scoreboard('123', '456', '111', new ContestState(ended: '123')),
            new Scoreboard('123', '456', '111', new ContestState(ended: '456')),
            [new Message(MessageType::WARNING, 'State ended does not match', '123', '456')],
        ];
        yield [
            new Scoreboard('123', '456', '111', new ContestState(frozen: '123')),
            new Scoreboard('123', '456', '111', new ContestState(frozen: '456')),
            [new Message(MessageType::WARNING, 'State frozen does not match', '123', '456')],
        ];
        yield [
            new Scoreboard('123', '456', '111', new ContestState(thawed: '123')),
            new Scoreboard('123', '456', '111', new ContestState(thawed: '456')),
            [new Message(MessageType::WARNING, 'State thawed does not match', '123', '456')],
        ];
        yield [
            new Scoreboard('123', '456', '111', new ContestState(finalized: '123')),
            new Scoreboard('123', '456', '111', new ContestState(finalized: '456')),
            [new Message(MessageType::WARNING, 'State finalized does not match', '123', '456')],
        ];
        yield [
            new Scoreboard('123', '456', '111', new ContestState(endOfUpdates: '123')),
            new Scoreboard('123', '456', '111', new ContestState(endOfUpdates: '456')),
            [new Message(MessageType::WARNING, 'State end of updates does not match', '123', '456')],
        ];
        yield [
            new Scoreboard(rows: []),
            new Scoreboard(rows: [new Row(1, '123', new Score(0), [])]),
            [new Message(MessageType::ERROR, 'Number of rows does not match', '0', '1')],
        ];
        yield [
            new Scoreboard(rows: [new Row(1, '123', new Score(0), [])]),
            new Scoreboard(rows: [new Row(1, '456', new Score(0), [])]),
            [new Message(MessageType::ERROR, 'Row 0: team ID does not match', '123', '456')],
        ];
        yield [
            new Scoreboard(rows: [new Row(1, '123', new Score(0), [])]),
            new Scoreboard(rows: [new Row(2, '123', new Score(0), [])]),
            [new Message(MessageType::ERROR, 'Row 0: rank does not match', '1', '2')],
        ];
        yield [
            new Scoreboard(rows: [new Row(1, '123', new Score(1), [])]),
            new Scoreboard(rows: [new Row(1, '123', new Score(2), [])]),
            [new Message(MessageType::ERROR, 'Row 0: num solved does not match', '1', '2')],
        ];
        yield [
            new Scoreboard(rows: [new Row(1, '123', new Score(1, 123), [])]),
            new Scoreboard(rows: [new Row(1, '123', new Score(1, 456), [])]),
            [new Message(MessageType::ERROR, 'Row 0: total time does not match', '123', '456')],
        ];
        yield [
            new Scoreboard(rows: [new Row(1, '123', new Score(1), [
                new Problem('A', 'a', 1, 0, true),
            ])]),
            new Scoreboard(rows: [new Row(1, '123', new Score(1), [
                new Problem('A', 'a', 1, 0, true),
            ])]),
            [],
        ];
        yield [
            new Scoreboard(rows: [new Row(1, '123', new Score(1), [
                new Problem('A', 'a', 1, 0, true),
            ])]),
            new Scoreboard(rows: [new Row(1, '123', new Score(1), [
                new Problem('A', 'a', 1, 0, false),
            ])]),
            [new Message(MessageType::ERROR, 'Row 0: Problem a solved does not match', '1', '')],
        ];
        yield [
            new Scoreboard(rows: [new Row(1, '123', new Score(1), [
                new Problem('A', 'a', 1, 0, true),
            ])]),
            new Scoreboard(rows: [new Row(1, '123', new Score(1), [])]),
            [new Message(MessageType::ERROR, 'Row 0: Problem a solved in first file, but not found in second file')],
        ];
        yield [
            new Scoreboard(rows: [new Row(1, '123', new Score(1), [])]),
            new Scoreboard(rows: [new Row(1, '123', new Score(1), [
                new Problem('A', 'a', 1, 0, true),
            ])]),
            [new Message(MessageType::ERROR, 'Row 0: Problem a solved in second file, but not found in first file')],
        ];
        yield [
            new Scoreboard(rows: [new Row(1, '123', new Score(1), [
                new Problem('A', 'a', 1, 0, true),
            ])]),
            new Scoreboard(rows: [new Row(1, '123', new Score(1), [
                new Problem('A', 'a', 2, 0, true),
            ])]),
            [new Message(MessageType::ERROR, 'Row 0: Problem a num judged does not match', '1', '2')],
        ];
        yield [
            new Scoreboard(rows: [new Row(1, '123', new Score(1), [
                new Problem('A', 'a', 1, 3, true),
            ])]),
            new Scoreboard(rows: [new Row(1, '123', new Score(1), [
                new Problem('A', 'a', 1, 4, true),
            ])]),
            [new Message(MessageType::ERROR, 'Row 0: Problem a num pending does not match', '3', '4')],
        ];
        yield [
            new Scoreboard(rows: [new Row(1, '123', new Score(1), [
                new Problem('A', 'a', 1, 3, true, 123),
            ])]),
            new Scoreboard(rows: [new Row(1, '123', new Score(1), [
                new Problem('A', 'a', 1, 3, true, 456),
            ])]),
            [new Message(MessageType::INFO, 'Row 0: Problem a time does not match', '123', '456')],
        ];
        // PC^2 uses different problem ID's. Also test on `Id = {problemId}-{digits}`
        yield [
            new Scoreboard(rows: [new Row(1, '123', new Score(1), [
                new Problem('A', 'a', 1, 0, true),
            ])]),
            new Scoreboard(rows: [new Row(1, '123', new Score(1), [
                new Problem('A', 'Id = a-123', 1, 0, true),
            ])]),
            [],
        ];
        yield [
            new Scoreboard(rows: [new Row(1, '123', new Score(1), [])]),
            new Scoreboard(rows: [new Row(1, '123', new Score(1), [
                new Problem('A', 'Id = a-123', 1, 0, true),
            ])]),
            [new Message(MessageType::ERROR, 'Row 0: Problem Id = a-123 solved in second file, but not found in first file')],
        ];
    }
}
