<?php

namespace App\Controller;

use JMS\Serializer\SerializerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Annotations as OA;
use App\Entity\User;

/**
 * Class UserController
 * @package App\Controller
 * @Route("/api/v1/users")
 */
class UserController extends ApiController
{
    public function __construct(SerializerInterface $serializer)
    {
        parent::__construct($serializer);
    }

    /**
     * @Route("/current", name="api_current_user", methods={"GET"})
     *
     * @OA\Get(
     *     tags={"Users"},
     *     summary="Authenticated user object",
     *     @Security(name="Bearer"),
     *     @OA\Response(
     *          response="200",
     *          description="User object",
     *          @OA\JsonContent(ref=@Model(type=User::class, groups={"Default"}))
     *      )
     * )
     */
    public function currentUser(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->responseSuccessWithObject($user);
    }
}
