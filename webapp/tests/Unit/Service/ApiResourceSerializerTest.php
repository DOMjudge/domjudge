<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Team;
use App\Service\ApiResourceSerializer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ApiResourceSerializerTest extends KernelTestCase
{
    public function testSerializationContextAndToArray(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var ApiResourceSerializer $serializer */
        $serializer = $container->get(ApiResourceSerializer::class);

        $data = ['id' => '123', 'name' => 'Test Team', 'end_time' => null];
        $array = $serializer->toArray($data);

        self::assertSame('123', $array['id']);
        self::assertSame('Test Team', $array['name']);
        self::assertArrayHasKey('end_time', $array);
        self::assertNull($array['end_time']);

        $json = $serializer->serialize($data);
        self::assertJson($json);
        self::assertStringContainsString('"id":"123"', $json);
        self::assertStringContainsString('"end_time":null', $json);
    }
}
