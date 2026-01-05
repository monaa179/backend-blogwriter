<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Catch exceptions and return standardized JSON error responses
 */
class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private string $environment,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Only handle API requests (those expecting JSON)
        if (!$this->isApiRequest($request)) {
            return;
        }

        $statusCode = 500;
        $error = 'internal_error';
        $message = 'An unexpected error occurred';

        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $message = $exception->getMessage();

            $error = match ($statusCode) {
                400 => 'bad_request',
                401 => 'unauthorized',
                403 => 'forbidden',
                404 => 'not_found',
                405 => 'method_not_allowed',
                409 => 'conflict',
                422 => 'validation_error',
                default => 'http_error',
            };
        }

        $data = [
            'error' => $error,
            'details' => ['message' => $message],
        ];

        // Include exception details in dev mode
        if ($this->environment === 'dev' && !($exception instanceof HttpExceptionInterface)) {
            $data['debug'] = [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
        }

        $response = new JsonResponse($data, $statusCode);
        $event->setResponse($response);
    }

    private function isApiRequest($request): bool
    {
        // Check if request accepts JSON or has JSON content type
        $acceptHeader = $request->headers->get('Accept', '');
        $contentType = $request->headers->get('Content-Type', '');

        return str_contains($acceptHeader, 'application/json')
            || str_contains($contentType, 'application/json')
            || str_starts_with($request->getPathInfo(), '/auth/')
            || str_starts_with($request->getPathInfo(), '/articles')
            || str_starts_with($request->getPathInfo(), '/modules');
    }
}
