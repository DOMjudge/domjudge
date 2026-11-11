<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Team;
use App\Service\ApiResourceSerializer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ApiResourceSerializerTest extends KernelTestCase
{
    public function testSerializationContextAndToArray(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var ApiResourceSerializer $serializer */
        $serializer = $container->get(ApiResourceSerializer::class);
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $team = $em->getRepository(Team::class)->findOneBy(['name' => 'Example teamname']);
        self::assertNotNull($team);

        $nonStrict = $serializer->toArray($team, strict: false, restricted: true);
        self::assertSame($team->getExternalid(), $nonStrict['id']);
        self::assertSame('Example teamname', $nonStrict['name']);
        self::assertArrayHasKey('location', $nonStrict, 'GROUP_NONSTRICT field must be included when strict=false');
        self::assertNull($nonStrict['location'], 'Null fields must be serialized as null');

        $strict = $serializer->toArray($team, strict: true, restricted: true);
        self::assertSame($team->getExternalid(), $strict['id']);
        self::assertSame('Example teamname', $strict['name']);
        self::assertArrayNotHasKey('location', $strict, 'GROUP_NONSTRICT field must be omitted when strict=true');

        $json = $serializer->serialize($team, strict: false, restricted: true);
        self::assertJson($json);
        self::assertStringContainsString('"name":"Example teamname"', $json);
        self::assertStringContainsString('"location":null', $json);
    }
}
