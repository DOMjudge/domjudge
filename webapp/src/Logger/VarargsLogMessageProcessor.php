<?php declare(strict_types=1);

/*
 * A simply message processor for Monolog, that uses printf style argument
 * passing.
 */

namespace App\Logger;

use Monolog\Processor\ProcessorInterface;
use ValueError;

class VarargsLogMessageProcessor implements ProcessorInterface
{
    public function __invoke(array $record): array
    {
        if (strpos($record['message'], '%') === false || empty($record['context'])) {
            return $record;
        }

        $res = false;
        try {
            $res = vsprintf($record['message'], $record['context']);
        } catch (ValueError $e) {}

        if ($res !== false) {
            $record['message'] = $res;
            $record['context'] = [];
        }

        return $record;
    }
}
