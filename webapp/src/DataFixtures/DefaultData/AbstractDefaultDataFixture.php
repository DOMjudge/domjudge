<?php declare(strict_types=1);

namespace App\DataFixtures\DefaultData;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;

abstract class AbstractDefaultDataFixture extends Fixture implements FixtureGroupInterface
{
    /**
     * @inheritDoc
     */
    public static function getGroups(): array
    {
        return ['default'];
    }
}
