<?php declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;

/**
 * Class AbstractExampleDataFixture
 * @package App\DataFixtures
 */
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
