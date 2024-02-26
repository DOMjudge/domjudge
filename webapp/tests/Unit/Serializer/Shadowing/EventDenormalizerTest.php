<?php declare(strict_types=1);

namespace App\Tests\Unit\Serializer\Shadowing;

use App\DataTransferObject\Shadowing\Event;
use App\DataTransferObject\Shadowing\EventType;
use App\DataTransferObject\Shadowing\LanguageEvent;
use App\DataTransferObject\Shadowing\Operation;
use App\DataTransferObject\Shadowing\SubmissionEvent;
use Generator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

class EventDenormalizerTest extends KernelTestCase
{
    /**
     * @dataProvider provideDenormalize
     */
    public function testDenormalizeUseContext(
        mixed $data,
        array $context,
        ?string $expectedId,
        EventType $expectedType,
        Operation $expectedOperation,
        ?string $expectedObjectId,
        array $expectedData
    ) {
        $serializer = $this->getcontainer()->get(SerializerInterface::class);
        $event = $serializer->denormalize($data, Event::class, 'json', $context);
        self::assertEquals($expectedId, $event->id);
        self::assertEquals($expectedType, $event->type);
        self::assertEquals($expectedOperation, $event->operation);
        self::assertEquals($expectedObjectId, $event->objectId);
        self::assertEquals($expectedData, $event->data);
    }

    /**
     * @dataProvider provideDenormalize
     */
    public function testDenormalizeDoNotUseContext(
        mixed $data,
        array $context,
        ?string $expectedId,
        EventType $expectedType,
        Operation $expectedOperation,
        ?string $expectedObjectId,
        array $expectedData
    ) {
        $serializer = $this->getcontainer()->get(SerializerInterface::class);
        $event = $serializer->denormalize($data, Event::class, 'json', ['api_version' => null]);
        self::assertEquals($expectedId, $event->id);
        self::assertEquals($expectedType, $event->type);
        self::assertEquals($expectedOperation, $event->operation);
        self::assertEquals($expectedObjectId, $event->objectId);
        self::assertEquals($expectedData, $event->data);
    }

    public function provideDenormalize(): Generator
    {
        yield '2022-07 format, create/update single' => [
            [
                'type' => 'submissions',
                'token' => 'sometoken',
                'data' => [
                    'id' => '123',
                    'language_id' => 'cpp',
                    'problem_id' => 'A',
                    'team_id' => '1',
                    'time' => '456',
                    'files' => [],
                ],
            ],
            ['api_version' => '2022-07'],
            'sometoken',
            EventType::SUBMISSIONS,
            Operation::CREATE,
            '123',
            [
                new SubmissionEvent(
                    id: '123',
                    languageId: 'cpp',
                    problemId: 'A',
                    teamId: '1',
                    time: '456',
                    entryPoint: null,
                    files: []
                ),
            ],
        ];
        yield '2022-07 format, create/update multiple' => [
            [
                'type' => 'languages',
                'token' => 'anothertoken',
                'data' => [
                    ['id' => 'cpp'],
                    ['id' => 'java'],
                ],
            ],
            ['api_version' => '2022-07'],
            'anothertoken',
            EventType::LANGUAGES,
            Operation::CREATE,
            null,
            [
                new LanguageEvent(id: 'cpp'),
                new LanguageEvent(id: 'java'),
            ],
        ];
        yield '2022-07 format, delete' => [
            [
                'type' => 'problems',
                'id' => '987',
                'token' => 'yetanothertoken',
                'data' => null,
            ],
            ['api_version' => '2022-07'],
            'yetanothertoken',
            EventType::PROBLEMS,
            Operation::DELETE,
            '987',
            [],
        ];
        yield '2020-03 format, create' => [
            [
                'id' => 'sometoken',
                'type' => 'submissions',
                'op' => 'create',
                'data' => [
                    'id' => '123',
                    'language_id' => 'cpp',
                    'problem_id' => 'A',
                    'team_id' => '1',
                    'time' => '456',
                    'files' => [],
                ],
            ],
            ['api_version' => '2020-03'],
            'sometoken',
            EventType::SUBMISSIONS,
            Operation::CREATE,
            '123',
            [
                new SubmissionEvent(
                    id: '123',
                    languageId: 'cpp',
                    problemId: 'A',
                    teamId: '1',
                    time: '456',
                    entryPoint: null,
                    files: []
                ),
            ],
        ];
        yield '2020-03 format, update' => [
            [
                'id' => 'anothertoken',
                'type' => 'languages',
                'op' => 'update',
                'data' => [
                    'id' => 'cpp',
                ],
            ],
            ['api_version' => '2020-03'],
            'anothertoken',
            EventType::LANGUAGES,
            Operation::UPDATE,
            'cpp',
            [
                new LanguageEvent(id: 'cpp'),
            ],
        ];
        yield '2020-03 format, delete' => [
            [
                'id' => 'yetanothertoken',
                'type' => 'problems',
                'op' => 'delete',
                'data' => [
                    'id' => '987',
                ],
            ],
            ['api_version' => '2020-03'],
            'yetanothertoken',
            EventType::PROBLEMS,
            Operation::DELETE,
            '987',
            [],
        ];
    }
}
