<?php

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class ValidationException extends SerializableException
{
    public function __construct(
        ConstraintViolationListInterface $errors,
        $message = ''
    ) {
        $validationErrors = [];
        foreach ($errors as $validationError) {
            $validationErrors[] = $validationError->getMessage();
        }

        if (!$message) {
            $message = 'Ошибка валидации.';
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
