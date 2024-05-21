<?php declare(strict_types=1);

namespace App\Doctrine;

use RuntimeException;

class ExternalIdAlreadyExistsException extends RuntimeException
{
    public function __construct(
        public readonly string $entityClass,
        public readonly string $externalid
    ) {
        $message = sprintf(
            'An entity of class %s already exists with the external ID "%s". Use another value for the externalid field.',
            $entityClass,
            $this->externalid
        );
        parent::__construct($message);
    }
}
