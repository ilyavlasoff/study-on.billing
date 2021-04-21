<?php

namespace App\Controller;

use JMS\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

abstract class ApiController extends AbstractController
{
    private $serializer;

    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    protected function responseSuccessWithObject(
        $data,
        $status = Response::HTTP_OK
    ): JsonResponse {
        return new JsonResponse(
            $this->serializer->serialize(
                $data,
                'json'
            ),
            $status,
            [],
            true
        );
    }
}
