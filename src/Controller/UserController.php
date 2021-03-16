<?php

namespace App\Controller;

use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class UserController
 * @package App\Controller
 * @Route("/api/v1/users")
 */
class UserController extends ApiController
{
    private $serializer;

    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * @Route("/current", name="api_current_user")
     */
    public function currentUser(): Response
    {
        $user = $this->getUser();
        return $this->serializedResponse($user, $this->serializer);
    }
}