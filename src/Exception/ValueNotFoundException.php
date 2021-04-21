<?php

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;

class ValueNotFoundException extends SerializableException
{
    public function __construct()
    {
        parent::__construct(
            'Указанное значение не найдено',
            Response::HTTP_NOT_FOUND,
            'ERR_NOT_FOUND',
            [],
            null
        );
    }
}
