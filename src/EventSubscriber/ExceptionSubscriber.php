<?php

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ExceptionSubscriber implements EventSubscriberInterface
{
    private LoggerInterface $logger;
    private string $environment;

    public function __construct(LoggerInterface $logger, string $environment)
    {
        $this->logger = $logger;
        $this->environment = $environment;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        $this->logger->error($exception->getMessage(), [
            'exception' => $exception,
            'route' => $request->attributes->get('_route'),
            'url' => $request->getUri(),
            'method' => $request->getMethod(),
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
        ]);

        if ($exception instanceof AccessDeniedException) {
            $response = new Response('Доступ запрещен', Response::HTTP_FORBIDDEN);
            $event->setResponse($response);
            return;
        }

        if (str_contains($request->getPathInfo(), '/api/')) {
            $statusCode = $exception instanceof HttpException ? 
                $exception->getStatusCode() : 
                Response::HTTP_INTERNAL_SERVER_ERROR;

            $data = [
                'error' => [
                    'code' => $statusCode,
                    'message' => $exception->getMessage(),
                ]
            ];

            if ($this->environment === 'dev') {
                $data['error']['trace'] = $exception->getTraceAsString();
                $data['error']['file'] = $exception->getFile();
                $data['error']['line'] = $exception->getLine();
            }

            $response = new JsonResponse($data, $statusCode);
            $event->setResponse($response);
            return;
        }

        if (!$exception instanceof HttpException) {
            $response = new Response(
                'Внутренняя ошибка сервера',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
            $event->setResponse($response);
        }
    }
}