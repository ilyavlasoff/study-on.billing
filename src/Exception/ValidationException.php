<?php

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Throwable;
use JMS\Serializer\Annotation as JMS;

class ValidationException extends SerializableException
{
    protected $code = Response::HTTP_BAD_REQUEST;

    protected $message = 'Ошибка валидации';

    /**
     * @JMS\Type('array<string>')
     * @JMS\Expose()
     * @JMS\Groups({"exception"})
     */
    public $validationErrors;

    public function __construct(
        ConstraintViolationListInterface $validationErrors,
        $message = "",
        $code = 0,
        Throwable $previous = null
    ) {
        foreach ($validationErrors as $validationError) {
            $this->validationErrors[] = $validationError->getMessage();
        }
        parent::__construct($message, $code, $previous);
    }
}
