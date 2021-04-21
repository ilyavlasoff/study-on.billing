<?php

namespace App\Exception;

class CashNotEnoughException extends SerializableException
{
    public function __construct()
    {
        parent::__construct(
            'На вашем счету недостаточно средств',
            406,
            'ERR_CASH',
            [],
            null
        );
    }
}
