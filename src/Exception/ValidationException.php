<?php

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class ValidationException extends SerializableException
{
    public function __construct(
        ConstraintViolationListInterface $errors,
        $message = 'Ошибка валидации.'
    ) {
        $validationErrors = [];
        foreach ($errors as $validationError) {
            $validationErrors[$validationError->getPropertyPath()] = $validationError->getMessage();
        }

        parent::__construct(
            $message,
            Response::HTTP_BAD_REQUEST,
            'ERR_VALIDATION',
            $validationErrors,
            null
        );
    }
}
