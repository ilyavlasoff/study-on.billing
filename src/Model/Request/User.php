<?php

namespace App\Model;

use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class User
 * @package App\Model
 */
class User
{
    /**
     * @Serializer\Type("string")
     * @Assert\Email(message="Email address {{ value }} is invalid")
     */
    private $email;

    /**
     * @Serializer\Type("string")
     * @Assert\Length(
     *     min="6",
     *     max="127",
     *     minMessage="Password must be longer than {{ limit }} symbols",
     *     maxMessage="Password must be shorted than {{ limit }} symbols"
     * )
     * @Assert\NotCompromisedPassword()
     */
    private $password;

    /**
     * @return mixed
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @return mixed
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param mixed $email
     */
    public function setEmail($email): void
    {
        $this->email = $email;
    }

    /**
     * @param mixed $password
     */
    public function setPassword($password): void
    {
        $this->password = $password;
    }

}