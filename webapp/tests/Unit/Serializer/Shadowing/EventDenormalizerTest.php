<?php declare(strict_types=1);

namespace App\Tests\Unit\Serializer\Shadowing;

use App\DataTransferObject\Shadowing\Event;
use App\DataTransferObject\Shadowing\EventType;
use App\DataTransferObject\Shadowing\LanguageEvent;
use App\DataTransferObject\Shadowing\Operation;
use App\DataTransferObject\Shadowing\SubmissionEvent;
use App\Utils\CcsApiVersion;
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
    ): void {
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
    ): void {
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
        foreach ([CcsApiVersion::Format_2023_06, CcsApiVersion::Format_2025_DRAFT] as $version) {
            yield $version->value . ' format, create/update single' => [
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
                ['api_version' => $version],
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
            yield $version->value . ' format, create/update unknown class' => [
                [
                    'type' => 'team-members',
                    'token' => 'sometoken',
                    'data' => [
                        ['id' => '123'],
                    ],
                ],
                ['api_version' => $version],
                'sometoken',
                EventType::TEAM_MEMBERS,
                Operation::CREATE,
                '123',
                [],
            ];
            yield $version->value . ' format, create/update multiple' => [
                [
                    'type' => 'languages',
                    'token' => 'anothertoken',
                    'data' => [
                        ['id' => 'cpp'],
                        ['id' => 'java'],
                    ],
                ],
                ['api_version' => $version],
                'anothertoken',
                EventType::LANGUAGES,
                Operation::CREATE,
                null,
                [
                    new LanguageEvent(id: 'cpp'),
                    new LanguageEvent(id: 'java'),
                ],
            ];
            yield $version->value . ' format, delete' => [
                [
                    'type' => 'problems',
                    'id' => '987',
                    'token' => 'yetanothertoken',
                    'data' => null,
                ],
                ['api_version' => $version->value],
                'yetanothertoken',
                EventType::PROBLEMS,
                Operation::DELETE,
                '987',
                [],
            ];
        }
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
            ['api_version' => CcsApiVersion::Format_2020_03->value],
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
            ['api_version' => CcsApiVersion::Format_2020_03->value],
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
            ['api_version' => CcsApiVersion::Format_2020_03->value],
            'yetanothertoken',
            EventType::PROBLEMS,
            Operation::DELETE,
            '987',
            [],
        ];
    }
}
