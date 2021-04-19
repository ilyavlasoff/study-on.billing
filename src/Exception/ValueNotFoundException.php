<?php

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;

class ValueNotFoundException extends SerializableException
{
    protected $code = Response::HTTP_NOT_FOUND;

    protected $message = 'Указанное значение не найдено';
}