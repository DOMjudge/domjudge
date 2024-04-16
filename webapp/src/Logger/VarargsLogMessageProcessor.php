<?php declare(strict_types=1);

/*
 * A simply message processor for Monolog, that uses printf style argument
 * passing. Only apply this if the message looks like a printf format string
 * (aka contains at least one `%`) and the context is a plain list array.
 */

namespace App\Logger;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use ValueError;

#[AutoconfigureTag(name: 'monolog.processor')]
class VarargsLogMessageProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        if (!str_contains($record->message, '%') ||
            empty($record->context) || !array_is_list($record->context)) {
            return $record;
        }

        $res = false;
        try {
            $res = vsprintf($record->message, $record->context);
        } catch (ValueError) {}

        if ($res !== false) {
            $record = $record->with(
                message: $res,
                context: []
            );
        }

        return $record;
    }
}
