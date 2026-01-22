<?php declare(strict_types=1);

namespace App\FosRestBundle;

use FOS\RestBundle\Util\ExceptionValueMap;
use JMS\Serializer\Context;
use JMS\Serializer\GraphNavigatorInterface;
use JMS\Serializer\Handler\SubscribingHandlerInterface;
use JMS\Serializer\JsonSerializationVisitor;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[AsAlias('fos_rest.serializer.flatten_exception_handler')]
readonly class FlattenExceptionHandler implements SubscribingHandlerInterface
{
    public function __construct(
        #[Autowire(service: 'fos_rest.exception.messages_map')]
        private ExceptionValueMap $messagesMap,
        #[Autowire('%kernel.debug%')]
        private bool              $debug
    ) {}

    /**
     * @return array<array{direction: int, format: string, type: string, method: string}>
     */
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

    /**
     * @param array{name: string, params: string[]} $type
     *
     * @return array{code: int, message: string}
     */
    public function serializeToJson(
        JsonSerializationVisitor $visitor,
        FlattenException $exception,
        array $type,
        Context $context
    ): array {
        return $visitor->visitArray($this->convertToArray($exception, $context), $type);
    }

    /**
     * @return array{code: int, message: string}
     */
    private function convertToArray(
        FlattenException $exception,
        Context $context
    ): array {
        if ($context->hasAttribute('status_code')) {
            $statusCode = $context->getAttribute('status_code');
        } else {
            $statusCode = $exception->getStatusCode();
        }

        if ($exception->getPrevious() && $exception->getPrevious()->getClass() === ValidationFailedException::class) {
            $exception = $exception->getPrevious();
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
