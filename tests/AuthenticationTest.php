<?php

namespace App\Tests;

use App\DataFixtures\UserFixtures;
use App\Model\Response\AuthToken;
use App\Model\Request\User;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface;

class AuthenticationTest extends AbstractTest
{
    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var UserPasswordEncoderInterface
     */
    private $passwordEncoder;

    protected function getFixtures(): array
    {
        /** @var UserPasswordEncoderInterface $upe */
        $upe = self::$container->get("security.password_encoder");
        return [new UserFixtures($upe)];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->serializer = self::$container->get('jms_serializer');
        $this->tokenStorage = self::$container->get('security.token_storage');
        $this->passwordEncoder = self::$container->get("security.password_encoder");
    }

    public function testCorrectRegistration(): void
    {
        $client = self::getClient();
        $regUrl = $client->getContainer()->get('router')->generate('app_register');
        $authenticatedUser = new User();

        $authenticatedUser->setEmail('mytest@test.com');
        $authenticatedUser->setPassword('!23SuperP@$$w0rd32');
        $authenticationData = $this->serializer->serialize($authenticatedUser, 'json');

        $client->request('post', $regUrl, [], [], ['CONTENT_TYPE' => 'application/json'], $authenticationData);

        $this->assertJsonResponse($client->getResponse());
        $responseData = $client->getResponse()->getContent();
        /** @var AuthToken $auth */
        $auth = $this->serializer->deserialize($responseData, AuthToken::class, 'json');
        self::assertGreaterThan(0, strlen($auth->getToken()));
        self::assertContains('ROLE_USER', $auth->getRoles());
    }

    public function testIncorrectRegistration(): void
    {
        $client = self::getClient();
        $regUrl = $client->getContainer()->get('router')->generate('app_register');

        $incorrectData = [
            [
                'email' => 'kjsfgkjf',
                'password' => '!23SuperP@$$w0rd32',
                'messages' => [
                    'Email address "kjsfgkjf" is invalid',
                ]
            ],
            [
                'email' => 'testuser@user.com',
                'password' => '$Gm!h',
                'messages' => [
                    'Password must be longer than 6 symbols',
                ]
            ],
            [
                'email' => 'testuser@user.com',
                'password' => bin2hex(random_bytes(700)),
                'messages' => [
                    'Password must be shorted than 127 symbols',
                ]
            ],
            [
                'email' => 'user@test.com',
                'password' => '!23SuperP@$$w0rd32',
                'messages' => [
                    'User with email "user@test.com" is already exists. Try to login instead',
                ]
            ],
            /*[
                'email' => 'testuser@user.com',
                'password' => 'password',
                'messages' => [
                    'This password has been leaked in a data breach, it must not be used. Please use another password',
                ]
            ],*/
        ];

        foreach ($incorrectData as $data) {
            $authenticatedUser = new User();
            $authenticatedUser->setEmail($data['email']);
            $authenticatedUser->setPassword($data['password']);
            $authenticationData = $this->serializer->serialize($authenticatedUser, 'json');

            $client->request('post', $regUrl, [], [], ['CONTENT_TYPE' => 'application/json'], $authenticationData);

            $this->assertJsonResponse($client->getResponse(), 400);
            $responseData = $client->getResponse()->getContent();

            $error = json_decode($responseData, true);
            self::assertEquals($error['error'], 'ERR_VALIDATION');
            foreach ($data['messages'] as $message) {
                self::assertContains($message, $error['details']);
            }
        }

        $invalidFields = [
            [
                'efail' => 'test@test.com',
                'password' => '!23SuperP@$$w0rd32',
            ],
            [
                'email' => 'test@test.com',
                'passworld' => '!23SuperP@$$w0rd32',
            ]
        ];
        $messages = [
            [
                "Email must be specified",
                "Email can not be blank"
            ],
            [
                "Password must be specified",
                "Password can not be blank"
            ]
        ];

        for ($i = 0; $i !== count($invalidFields); $i++) {
            $authenticationData = json_encode($invalidFields[$i]);

            $client->request('post', $regUrl, [], [], ['CONTENT_TYPE' => 'application/json'], $authenticationData);

            $this->assertJsonResponse($client->getResponse(), 400);
            $responseData = $client->getResponse()->getContent();

            $error = json_decode($responseData, true);
            self::assertEquals($error['error'], 'ERR_VALIDATION');
            foreach ($messages[$i] as $message) {
                self::assertContains($message, $error['details']);
            }
        }
    }

    public function testCorrectLogin(): void
    {
        $client = self::getClient();
        $regUrl = $client->getContainer()->get('router')->generate('app_authenticate');
        $authenticationData = ['username' => 'user@test.com','password' => 'passwd'];

        $client->request(
            'post',
            $regUrl,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($authenticationData)
        );

        $this->assertJsonResponse($client->getResponse());
        $responseData = $client->getResponse()->getContent();

        /** @var AuthToken $auth */
        $auth = $this->serializer->deserialize($responseData, AuthToken::class, 'json');
        self::assertGreaterThan(0, strlen($auth->getToken()));
        self::assertContains('ROLE_USER', $auth->getRoles());
    }

    public function testIncorrectLogin(): void
    {
        $client = self::getClient();
        $regUrl = $client->getContainer()->get('router')->generate('app_authenticate');

        $authenticationData = ['username' => 'qwerty','password' => 'qwerty'];

        $client->request(
            'post',
            $regUrl,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($authenticationData)
        );

        $this->assertJsonResponse($client->getResponse(), 401);
        $responseData = $client->getResponse()->getContent();

        $auth = json_decode($responseData, true);
        self::assertEquals('Invalid credentials.', $auth['message']);
    }

    public function testRefreshToken()
    {
        $client = self::getClient();

        $data = json_encode(['username' => 'user@test.com', 'password' => 'passwd']);

        $client->request(
            'post',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $data
        );

        $responseData = json_decode($client->getResponse()->getContent(), true);

        $accessToken = $responseData['token'];
        $refreshToken = $responseData['refresh_token'];

        self::assertNotEmpty($refreshToken);
        self::assertNotEmpty($accessToken);

        $client->request(
            'post',
            '/api/v1/token/refresh',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '. $accessToken],
            json_encode(['refresh_token' => $refreshToken])
        );

        self::assertJsonResponse($client->getResponse());

        $responseData = json_decode($client->getResponse()->getContent(), true);

        self::assertNotEmpty($responseData['token']);
    }
}
