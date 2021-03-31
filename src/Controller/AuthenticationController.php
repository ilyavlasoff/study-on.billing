<?php

namespace App\Controller;

use App\Entity\User;
use App\Model\User as UserDto;
use App\Model\AuthToken;
use App\Model\FailResponse;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Service\RefreshToken;
use JMS\Serializer\SerializerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OpenApi\Annotations as OA;
use Nelmio\ApiDocBundle\Annotation\Model;

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
     * @param RefreshTokenManagerInterface $refreshTokenManager
     * @param ValidatorInterface $validator
     * @return Response
     * @throws \Exception
     *
     * @Route("/register", name="app_register", methods={"POST"})
     *
     * @OA\Post(
     *     path="/api/v1/register",
     *     summary="New user registration",
     *     @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(
     *                  property="email",
     *                  type="string",
     *                  example="ilya@test.com"
     *              ),
     *              @OA\Property(
     *                  property="password",
     *                  type="string",
     *                  example="#123SuperStrongPassword321#"
     *              )
     *          )
     *     ),
     *     @OA\Response(
     *          response=201,
     *          description="Successful authorization",
     *          @OA\JsonContent(ref=@Model(type=AuthToken::class, groups={"Default"}))
     *     ),
     *     @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\JsonContent(ref=@Model(type=FailResponse::class, groups={"Default"}))
     *     )
     * )
     */
    public function register(
        Request $request,
        UserPasswordEncoderInterface $userPasswordEncoder,
        SerializerInterface $serializer,
        JWTTokenManagerInterface $tokenManager,
        RefreshTokenManagerInterface $refreshTokenManager,
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

        $jwtToken = $tokenManager->create($user);

        $refreshToken = $refreshTokenManager->create();
        $refreshToken->setUsername($user->getEmail());
        $refreshToken->setRefreshToken();
        $nowPlusOneMonth = (new \DateTime())->add(new \DateInterval('P1M'));
        $refreshToken->setValid($nowPlusOneMonth);
        $refreshTokenManager->save($refreshToken);

        $tokenResponse = new AuthToken($jwtToken, $refreshToken->getRefreshToken(), $user->getRoles());
        return $this->serializedResponse($tokenResponse, $serializer, Response::HTTP_CREATED);
    }

    /**
     * @Route("/auth", name="app_authenticate", methods={"POST"})
     *
     * @OA\Post(
     *     path="/api/v1/auth",
     *     summary="User authentication",
     *     @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(
     *                  property="username",
     *                  type="string",
     *                  example="ilya@test.com"
     *              ),
     *              @OA\Property(
     *                  property="password",
     *                  type="string",
     *                  example="#123SuperStrongPassword321#"
     *              )
     *          )
     *      ),
     *     @OA\Response(
     *          response="200",
     *          description="User authentication token",
     *          @OA\JsonContent(ref=@Model(type=AuthToken::class, groups={"Default"}))
     *      ),
     *     @OA\Response(
     *          response="401",
     *          description="Unauthorized message",
     *          @OA\JsonContent(ref=@Model(type=FailResponse::class, groups={"Default"}))
     *      )
     * )
     */
    public function authenticate(): void
    {
        // Implemented by JWTAuthentication
    }

    /**
     * @param Request $request
     * @param RefreshToken $refreshToken
     * @return Response
     *
     * @Route("/token/refresh", name="jwt_refresh", methods={"POST"})
     *
     * @OA\Post(
     *     path="/api/v1/token/refresh",
     *     summary="Jwt token refresh method",
     *     @Security(name="Bearer"),
     *     @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(
     *                  property="refresh_token",
     *                  type="string",
     *                  example="sjkalfhupt7322834py7324p823hfhjsdhf"
     *              )
     *          )
     *      ),
     *     @OA\Response(
     *          response="200",
     *          description="Successfully updated JWT token",
     *          @OA\JsonContent(ref=@Model(type=AuthToken::class, groups={"Default"}))
     *      ),
     *      @OA\Response(
     *          response="401",
     *          description="Unauthorized message",
     *          @OA\JsonContent(ref=@Model(type=FailResponse::class, groups={"Default"}))
     *      )
     * )
     */
    public function refreshToken(Request $request, RefreshToken $refreshToken): Response
    {
        return $refreshToken->refresh($request);
    }
}
