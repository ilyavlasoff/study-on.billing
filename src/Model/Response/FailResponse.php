<?php

namespace App\Model;

use JMS\Serializer\Annotation as Serializer;
use OpenApi\Annotations as OA;

/**
 * Class FailResponse
 * @package App\Model
 * @OA\Schema(
 *     title="Fail response model",
 *     description="Contains errors occurred during request execution"
 * )
 */
class FailResponse
{
    /**
     * @Serializer\Type("bool")
     * @OA\Property(
     *     format="bool",
     *     title="Success flag",
     *     description="Always false if any error occurred"
     * )
     */
    private $success = false;

    /**
     * @Serializer\Type("array")
     * @OA\Property(
     *     format="array",
     *     title="Error messages array",
     *     description="Array containing error messages occurred",
     * )
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