<?php declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\HttpFoundation\JsonResponse;

#[AsEventListener(event: 'kernel.exception', priority: 0)]
class ValidationExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $e = $event->getThrowable();

        if (!$e instanceof ValidationFailedException) {
            return;
        }

        $errors = [];
        foreach ($e->getViolations() as $violation) {
            $errors[] = [
                'property' => $violation->getPropertyPath(),
                'message'  => $violation->getMessage(),
                'code'     => $violation->getCode(),
            ];
        }

        $response = new JsonResponse([
            'title'  => 'Bad Request',
            'status' => 400,
            'errors' => $errors,
        ], 400);

        $event->setResponse($response);
    }
}
