<?php

namespace App\Model;

use JMS\Serializer\Annotation as Serializer;

/**
 * Class FailResponse
 * @package App\Model
 */
class FailResponse
{
    /**
     * @Serializer\Type("bool")
     */
    private $success = false;

    /**
     * @Serializer\Type("array")
     */
    private $error;

    public function __construct(array $error)
    {
        $this->error = $error;
    }

    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * @param bool $success
     */
    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }

    /**
     * @return mixed
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @param mixed $error
     */
    public function setError($error): void
    {
        $this->error = $error;
    }


}