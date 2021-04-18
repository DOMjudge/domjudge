<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;

abstract class AbstractTestDataFixture extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        $splitClassName = explode("\\", static::class);
        $shortClassName = array_pop($splitClassName);
        return [$shortClassName];
    }
}
