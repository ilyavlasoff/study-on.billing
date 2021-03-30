<?php

namespace App\Model;

use JMS\Serializer\Annotation as Serializer;

/**
 * Class AuthToken
 * @package App\Model
 */
class AuthToken
{
    /**
     * @Serializer\Type("string")
     */
    private $token;

    /**
     * @Serializer\Type("string")
     */
    private $refreshToken;

    /**
     * @Serializer\Type("array")
     */
    private $roles;

    public function __construct(string $token, string $refreshToken, array $roles)
    {
        $this->token = $token;
        $this->roles = $roles;
        $this->refreshToken = $refreshToken;
    }

    /**
     * @return mixed
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @return mixed
     */
    public function getRoles()
    {
        return $this->roles;
    }

    /**
     * @param mixed $token
     */
    public function setToken($token): void
    {
        $this->token = $token;
    }

    /**
     * @param mixed $roles
     */
    public function setRoles($roles): void
    {
        $this->roles = $roles;
    }

    /**
     * @return mixed
     */
    public function getRefreshToken()
    {
        return $this->refreshToken;
    }

    /**
     * @param mixed $refreshToken
     */
    public function setRefreshToken($refreshToken): void
    {
        $this->refreshToken = $refreshToken;
    }



}