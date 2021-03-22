<?php

namespace App\Controller;

use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use App\Entity\User;

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
     * @Route("/current", name="api_current_user", methods={"GET"})
     * @SWG\Get(
     *     path="/api/v1/users/current",
     *     summary="Get current user",
     *     description="Get logged in user object",
     *     produces={"application/json"},
     *     @SWG\Response(
     *          response=200,
     *          description="Success",
     *          @SWG\Schema(ref=@Model(type="App\Entity\User::class"))
     *     ),
     *     @SWG\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @SWG\Schema(
     *              type="application/json",
     *              @SWG\Property(property="code", type="integer"),
     *              @SWG\Property(property="message", type="string")
     *          )
     *     )
     * )
     */
    public function currentUser(): Response
    {
        $user = $this->getUser();
        return $this->serializedResponse($user, $this->serializer);
    }
}