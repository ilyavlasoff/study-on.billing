<?php

namespace App\Controller;

use App\Entity\User;
use App\Model\User as UserDto;
use App\Model\AuthToken;
use JMS\Serializer\SerializerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Swagger\Annotations as SWG;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;

/**
 * Class AuthenticationController
 * @package App\Controller
 * @Route("/api/v1")
 */
class AuthenticationController extends ApiController
{
    /**
     * @param Request $request
     * @param UserPasswordEncoderInterface $userPasswordEncoder
     * @param SerializerInterface $serializer
     * @param JWTTokenManagerInterface $tokenManager
     * @param ValidatorInterface $validator
     * @return Response
     * @Route("/register", name="app_register", methods={"POST"})
     *
     * @SWG\Post(
     *     path="/api/v1/register",
     *     summary="Registration",
     *     description="Register new billing user",
     *     produces={"application/json"},
     *     consumes={"application/json"},
     *     @SWG\Parameter(
     *          type="application/json",
     *          format="application/json",
     *          in="body",
     *          name="",
     *          description="Registration credentials",
     *          @SWG\Schema(ref=@Model(type="App\Model\User::class"))
     *     ),
     *     @SWG\Response(
     *          response=201,
     *          description="Successfull authorization",
     *          @SWG\Schema(ref=@Model(type="App\Model\AuthToken::class"))
     *     ),
     *     @SWG\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @SWG\Schema(ref=@Model(type="App\Model\FailResponse::class"))
     *     )
     * )
     */
    public function register(
        Request $request,
        UserPasswordEncoderInterface $userPasswordEncoder,
        SerializerInterface $serializer,
        JWTTokenManagerInterface $tokenManager,
        ValidatorInterface $validator
    ): Response {
        $userCredentials = $serializer->deserialize($request->getContent(), UserDto::class, 'json');

        if (count($validationErrors = $validator->validate($userCredentials))) {
            return $this->responseWithValidationErrors($validationErrors, $serializer);
        }

        $user = User::fromDto($userCredentials, $userPasswordEncoder);
        // Проверка уникальности поля email
        if (count($uniqueValidationError = $validator->validate($user))) {
            return $this->responseWithValidationErrors($uniqueValidationError, $serializer);
        }

        $manager = $this->getDoctrine()->getManager();
        $manager->persist($user);
        $manager->flush();

        $token = $tokenManager->create($user);
        $tokenResponse = new AuthToken($token, $user->getRoles());
        return $this->serializedResponse($tokenResponse, $serializer, Response::HTTP_CREATED);
    }

    /**
     * @Route("/auth", name="app_authenticate", methods={"POST"})
     * @SWG\Post(
     *     path="/api/v1/auth",
     *     summary="Authenticate user",
     *     description="Authenticate user with login and password",
     *     produces={"application/json"},
     *     consumes={"application/json"},
     *     @SWG\Parameter(
     *          type="application/json",
     *          in="body",
     *          name="",
     *          description="User credentials",
     *          @SWG\Schema(ref=@Model(type="App\Model\User::class"))
     *     ),
     *     @SWG\Response(
     *          response=201,
     *          description="Success",
     *          @SWG\Schema(ref=@Model(type="App\Model\AuthToken::class"))
     *      ),
     *     @SWG\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @SWG\Schema(ref=@Model(type="App\Model\FailResponse::class"))
     *     )
     * )
     */
    public function authenticate(): void
    {
        // Implemented by JWTAuthentication
    }
}
