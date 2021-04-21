<?php

namespace App\Model\Request;

use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class User
 */
class User
{
    /**
     * @Serializer\Type("string")
     * @Assert\NotNull(message="Email must be specified")
     * @Assert\NotBlank(message="Email can not be blank")
     * @Assert\Email(message="Email address {{ value }} is invalid")
     */
    private $email;

    /**
     * @Serializer\Type("string")
     * @Assert\NotNull(message="Password must be specified")
     * @Assert\NotBlank(message="Password can not be blank")
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
