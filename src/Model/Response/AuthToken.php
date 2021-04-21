<?php

namespace App\Model\Response;

use JMS\Serializer\Annotation as Serializer;
use OpenApi\Annotations as OA;

/**
 * Class AuthToken
 *
 * @OA\Schema(
 *     title="Authentication and refresh tokens",
 *     description="Contains JWT authentication token, refresh token and allowed roles for logged in user"
 * )
 */
class AuthToken
{
    /**
     * @Serializer\Type("string")
     * @OA\Property(
     *     format="string",
     *     title="JWT token",
     *     description="Base64 encoded token using for authorization"
     * )
     */
    private $token;

    /**
     * @Serializer\Type("string")
     * @OA\Property(
     *     format="string",
     *     title="Refresh token",
     *     description="Toekn using for refreshing JWT"
     * )
     */
    private $refreshToken;

    /**
     * @Serializer\Type("array")
     * @OA\Property(
     *     format="array",
     *     title="Roles",
     *     description="Roles defines allowed operations group for authenticated user"
     * )
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
