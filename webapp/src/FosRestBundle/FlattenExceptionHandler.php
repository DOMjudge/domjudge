<?php declare(strict_types=1);

namespace App\FosRestBundle;

use FOS\RestBundle\Util\ExceptionValueMap;
use JMS\Serializer\Context;
use JMS\Serializer\GraphNavigatorInterface;
use JMS\Serializer\Handler\SubscribingHandlerInterface;
use JMS\Serializer\JsonSerializationVisitor;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class FlattenExceptionHandler
 *
 * @package App\Controller\API
 */
class FlattenExceptionHandler implements SubscribingHandlerInterface
{
    private ExceptionValueMap $messagesMap;
    private bool $debug;

    public function __construct(ExceptionValueMap $messagesMap, bool $debug)
    {
        $this->messagesMap = $messagesMap;
        $this->debug       = $debug;
    }

    public static function getSubscribingMethods(): array
    {
        return [
            [
                'direction' => GraphNavigatorInterface::DIRECTION_SERIALIZATION,
                'format' => 'json',
                'type' => FlattenException::class,
                'method' => 'serializeToJson',
            ],
        ];
    }

    public function serializeToJson(
        JsonSerializationVisitor $visitor,
        FlattenException $exception,
        array $type,
        Context $context
    ): array {
        return $visitor->visitArray($this->convertToArray($exception, $context),
            $type);
    }

    private function convertToArray(
        FlattenException $exception,
        Context $context
    ): array {
        if ($context->hasAttribute('status_code')) {
            $statusCode = $context->getAttribute('status_code');
        } else {
            $statusCode = $exception->getStatusCode();
        }

        $showMessage = $this->messagesMap->resolveFromClassName($exception->getClass());

        if ($showMessage || $this->debug) {
            $message = $exception->getMessage();
        } else {
            $message = Response::$statusTexts[$statusCode] ?? 'error';
        }

        $result = [
            'code' => $statusCode,
            'message' => $message,
        ];

        if ($this->debug) {
            $result['class'] = $exception->getClass();
            $result['trace'] = $exception->getTrace();
        }

        return $result;
    }
}
