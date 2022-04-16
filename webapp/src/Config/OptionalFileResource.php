<?php declare(strict_types=1);

namespace App\Config;

use Symfony\Component\Config\Resource\FileExistenceResource;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Config\Resource\SelfCheckingResourceInterface;

/**
 * This resource type is based on Symfony's built in FileResource but also
 * tracks presence of the file.
 *
 * @see FileResource
 * @see FileExistenceResource
 */
class OptionalFileResource implements SelfCheckingResourceInterface
{
    private string $resource;
    private bool $exists;

    public function __construct(string $resource)
    {
        $this->resource = $resource;
        $this->exists = file_exists($resource);
    }

    public function __toString(): string
    {
        return $this->resource;
    }

    public function getResource(): string
    {
        return $this->resource;
    }

    public function isFresh(int $timestamp): bool
    {
        $exists = file_exists($this->resource);
        if ($exists !== $this->exists) {
            return false;
        }

        // If the file didn't exist when we first checked it and still doesn't exist now,
        // it is fresh.
        if (!$exists) {
            return true;
        }

        // Note: the original FileResource had a check if $filemtime was false
        // We do not want this, because if the file doesn't exist, it *will* return false
        // That case is already handled above.
        $filemtime = @filemtime($this->resource);

        if ($filemtime > $timestamp) {
            return false;
        }

        return true;
    }
}
