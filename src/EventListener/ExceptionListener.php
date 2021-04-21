<?php

namespace App\EventListener;

use App\Exception\SerializableException;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ExceptionListener
{
    private $serializer;

    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    public function onExceptionJsonResponse(ExceptionEvent $event)
    {
        $exception = $event->getThrowable();

        $response = new JsonResponse();

        if ($exception instanceof SerializableException) {
            $response->setStatusCode($exception->getCode());
            $context = (new SerializationContext())->setGroups('exception');
            $serializedException = $this->serializer->serialize($exception, 'json', $context);
            $response->setContent($serializedException);
        } else {
            $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;

            if ($exception instanceof HttpExceptionInterface) {
                $statusCode = $exception->getStatusCode();
                $response->headers->replace($exception->getHeaders());
            }

            $response->setStatusCode($statusCode);

            $genericException = new SerializableException('', $statusCode, '', [
                $exception->getMessage(),
            ]);

            $context = (new SerializationContext())->setGroups('exception');
            $response->setContent($this->serializer->serialize($genericException, 'json', $context));
        }

        $event->setResponse($response);
    }
}
