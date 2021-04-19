<?php

namespace App\Exception;

class CashNotEnoughException extends SerializableException
{
    protected $code = 406;

    protected $message = 'На вашем счету недостаточно средств';
}