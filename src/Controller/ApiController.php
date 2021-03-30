<?php

namespace App\Controller;

use App\Model\FailResponse;
use JMS\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationListInterface;

abstract class ApiController extends AbstractController
{
    protected function responseWithValidationErrors(
        ConstraintViolationListInterface $validationErrors,
        SerializerInterface $serializer
    ): Response {
        $errors = [];
        foreach ($validationErrors as $validationError) {
            $errors[] = $validationError->getMessage();
        }

        $failResponse = new FailResponse($errors);
        return new JsonResponse(
            $serializer->serialize($failResponse, 'json'),
            Response::HTTP_BAD_REQUEST,
            [],
            true
        );
    }

    protected function serializedResponse(
        $data,
        SerializerInterface $serializer,
        $status = Response::HTTP_OK
    ): JsonResponse {
        return new JsonResponse($serializer->serialize($data, 'json'), $status, [], true);
    }
}
