<?php declare(strict_types=1);

namespace App\DataFixtures\ExampleData;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;

abstract class AbstractExampleDataFixture extends Fixture implements FixtureGroupInterface
{
    /**
     * @inheritDoc
     */
    public static function getGroups(): array
    {
        return ['example'];
    }
}
