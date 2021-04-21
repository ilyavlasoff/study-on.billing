<?php

namespace App\Tests;

use App\DataFixtures\UserFixtures;
use App\Entity\Course;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class CourseTest extends AbstractTest
{
    private $accounts = [
        'user' => 'user@test.com',
        'admin' => 'admin@test.com',
    ];

    private $incorrectCourseData;

    private $correctCourseData;

    private $incorrectMessages;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    protected function getFixtures(): array
    {
        /** @var UserPasswordEncoderInterface $upe */
        $upe = self::$container->get('security.password_encoder');

        return [new UserFixtures($upe)];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = self::$container->get('doctrine.orm.entity_manager');

        $this->correctCourseData = [
            [
                'code' => 'тестовый_курс_1',
                'type' => 'rent',
                'title' => 'Тестовый курс №1',
                'price' => 101.01,
                'rent_time' => 'P10D',
            ],
            [
                'code' => 'тестовый_курс_2',
                'type' => 'free',
                'title' => 'Тестовый курс №2',
            ],
            [
                'code' => 'тестовый_курс_3',
                'type' => 'buy',
                'title' => 'Тестовый курс №3',
                'price' => 123.45,
            ],
        ];

        $this->incorrectCourseData = [
            [
                'type' => 'rent',
                'title' => 'Тестовый курс №1',
                'price' => 101.01,
                'rent_time' => 'P10D',
            ],
            [
                'code' => 'тестовый_курс_2',
                'title' => 'Тестовый курс №2',
                'price' => 3498.33,
            ],
            [
                'code' => 'тестовый_курс_3',
                'type' => 'rent',
                'price' => 123.45,
                'rent_time' => 'P30D',
            ],
            [
                'code' => bin2hex(random_bytes(1000)),
                'type' => 'free',
                'title' => 'тестовый_курс_некорректный',
            ],
            [
                'code' => 'тестовый_курс_некорректный',
                'type' => 'free',
                'title' => bin2hex(random_bytes(1000)),
            ],
            [
                'code' => 'тестовый_курс_некорректный',
                'type' => 'rent',
                'price' => 101.01,
                'title' => 'Incorrect test course',
            ],
            [
                'code' => 'тестовый_курс_некорректный',
                'type' => 'buy',
                'rent_time' => 'P10D',
                'title' => 'Incorrect test course',
            ],
            [
                'code' => 'тестовый_курс_некорректный',
                'type' => 'free',
                'rent_time' => 'P10D',
                'title' => 'Incorrect test course',
            ],
            [
                'code' => 'тестовый_курс_некорректный',
                'type' => 'free',
                'price' => 5000,
                'title' => 'Incorrect test course',
            ],
            [
                'code' => 'тестовый_курс_некорректный',
                'type' => 'rent',
                'rent_time' => 'P2D',
                'title' => 'Incorrect test course',
            ],
            [
                'code' => 'тестовый_курс_некорректный',
                'type' => 'death',
                'cost' => 213.02,
                'title' => 'Incorrect test course',
            ],
            [
                'code' => 'тестовый_курс_некорректный',
                'type' => 'rent',
                'rent_time' => 'P20D',
                'price' => -101.01,
                'title' => 'Incorrect test course',
            ],
        ];

        $this->incorrectMessages = [
            [
                'Course code can not be nullable',
                'Course code can not be blank',
            ],
            [
                'Type can not be blank',
                'Type not found',
            ],
            [
                'Course must contain title',
                'Course title can not be blank',
            ],
            [
                'Maximal code string length is 255 symbols',
            ],
            [
                'Maximal title length is 255 symbols',
            ],
            [
                'This course must contain rent time',
            ],
            [
                'Non-rent course can not contain rent time value',
            ],
            [
                'Non-rent course can not contain rent time value',
            ],
            [
                'Free course can not contain cost value',
            ],
            [
                'Rent course can not be free',
            ],
            [
                'Incorrect course type, available only [free, rent, buy] types',
            ],
            [
                'Cost can not be negative',
            ],
        ];
    }

    private function performAuthorization(bool $asAdmin)
    {
        $client = self::getClient();

        $account = $asAdmin ? $this->accounts['admin'] : $this->accounts['user'];
        $data = json_encode([
            'username' => $account,
            'password' => 'passwd',
        ]);
        $client->request('post', '/api/v1/auth', [], [], ['CONTENT_TYPE' => 'application/json'], $data);

        $responseData = json_decode($client->getResponse()->getContent(), true);

        return $responseData['token'];
    }

    private function getUserIdentifier(string $email)
    {
        $conn = $this->entityManager->getConnection();

        $q = 'select b.id from billing_user b where email = :email';

        $st = $conn->prepare($q);
        $st->bindValue('email', $email);
        $st->execute();
        $res = $st->fetchAll();

        if (!count($res)) {
            return null;
        }

        return $res[0]['id'];
    }

    private function getHeaders($token)
    {
        return [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ];
    }

    public function testGetCoursesList()
    {
        $client = self::getClient();
        $accessToken = $this->performAuthorization(false);

        $client->request(
            'get',
            '/api/v1/courses/',
            [],
            [],
            $this->getHeaders($accessToken),
            null
        );

        $this->assertJsonResponse($client->getResponse());
        $availableCourses = json_decode($client->getResponse()->getContent(), true);

        $conn = $this->entityManager->getConnection();

        $q = '
            select c2.*, case when bcv.c_id is null then false else true end as owns, bcv.vu as until
                from course c2 left join (
                    select bc.id as c_id, bc.vu
                    from (
                             select c.id, t.valid_until vu, row_number() over (partition by c.id order by t.valid_until desc) as n
                             from course c
                                      inner join transaction t on c.id = t.course_id
                             where t.user_id = :userId
                               and (c.type <> 1 or (c.type = 1 and t.valid_until > now()))
                         ) as bc
                    where bc.n = 1
                ) bcv on c2.id = bcv.c_id
                where c2.active = true;
        ';

        $st = $conn->prepare($q);
        $userId = $this->getUserIdentifier($this->accounts['user']);
        $st->bindValue('userId', $userId);
        $st->execute();
        $res = $st->fetchAll();

        self::assertEquals(count($res), count($availableCourses));

        $typeValues = [
            'free' => 0,
            'rent' => 1,
            'buy' => 2,
        ];

        foreach ($res as $course) {
            $c = array_filter($availableCourses, function ($item) use ($course, $typeValues) {
                $eq = $item['code'] === $course['code']
                    && $typeValues[$item['type']] === $course['type']
                    && $item['title'] === $course['title'];

                if (array_key_exists('price', $item)) {
                    $eq = $eq && $item['price'] == $course['cost'];
                }
                if (array_key_exists('rent_time', $item)) {
                    $eq = $eq && $item['rent_time'] == $course['rent_time'];
                }
                if (array_key_exists('valid_until', $item)) {
                    $eq = $eq && $item['valid_until'] === $course['owned_until'];
                }

                return $eq;
            });

            self::assertCount(1, $c);
        }
    }

    public function testCreateCourse()
    {
        $client = self::getClient();
        $accessToken = $this->performAuthorization(true);

        foreach ($this->correctCourseData as $courseItem) {
            $serializerData = json_encode($courseItem);
            $client->request(
                'post',
                '/api/v1/courses/',
                [],
                [],
                $this->getHeaders($accessToken),
                $serializerData
            );

            self::assertJsonResponse($client->getResponse(), 201);

            $typeValues = [
                'free' => 0,
                'rent' => 1,
                'buy' => 2,
            ];

            $result = $this->entityManager->getRepository(Course::class)
                ->findOneBy([
                    'code' => $courseItem['code'],
                    'type' => $typeValues[$courseItem['type']],
                    'title' => $courseItem['title'],
                ]);

            self::assertNotNull($result);
        }
    }

    public function testCreateIncorrectCourse()
    {
        $client = self::getClient();
        $accessToken = $this->performAuthorization(true);

        for ($i = 0; $i != count($this->incorrectCourseData); ++$i) {
            $serializerData = json_encode($this->incorrectCourseData[$i]);

            $client->request(
                'post',
                '/api/v1/courses/',
                [],
                [],
                $this->getHeaders($accessToken),
                $serializerData
            );

            self::assertJsonResponse($client->getResponse(), 400);

            $response = json_decode($client->getResponse()->getContent(), true);

            $errors = $response['details'];

            self::assertEquals(count($errors), count($this->incorrectMessages[$i]));

            foreach ($this->incorrectMessages[$i] as $message) {
                $exists = in_array($message, $errors);

                self::assertTrue($exists);
            }
        }
    }

    public function testGetCourse()
    {
        $client = self::getClient();
        $adminAccessToken = $this->performAuthorization(true);
        $userAccessToken = $this->performAuthorization(false);

        $course = [
            'code' => 'тестовый_курс_1',
            'type' => 'rent',
            'title' => 'Тестовый курс №1',
            'price' => 101.01,
            'rent_time' => 'P10D',
        ];

        $serializerData = json_encode($course);

        $client->request(
            'post',
            '/api/v1/courses/',
            [],
            [],
            $this->getHeaders($adminAccessToken),
            $serializerData
        );

        self::assertJsonResponse($client->getResponse(), 201);

        foreach ([$adminAccessToken, $userAccessToken] as $token) {
            $client->request(
                'get',
                "/api/v1/courses/{$course['code']}",
                [],
                [],
                $this->getHeaders($token)
            );

            self::assertJsonResponse($client->getResponse());

            $receivedCourse = json_decode($client->getResponse()->getContent(), true);

            $courseCorrect = $course['code'] === $receivedCourse['code']
                && $course['type'] === $receivedCourse['type']
                && $course['title'] === $receivedCourse['title']
                && $course['price'] === $receivedCourse['price']
                && $course['rent_time'] === $receivedCourse['rent_time'];

            self::assertTrue($courseCorrect);
        }
    }

    public function testEditCourse()
    {
        $client = self::getClient();
        $adminAccessToken = $this->performAuthorization(true);

        $course = [
            'code' => 'test_course',
            'type' => 'rent',
            'title' => 'Тестовый курс №100',
            'price' => 2349.01,
            'rent_time' => 'P25D',
        ];

        $serializerData = json_encode($course);

        $client->request(
            'post',
            '/api/v1/courses/',
            [],
            [],
            $this->getHeaders($adminAccessToken),
            $serializerData
        );

        self::assertJsonResponse($client->getResponse(), 201);

        $actualCode = $course['code'];

        foreach ($this->correctCourseData as $editedCourseData) {
            $serializedCourse = json_encode($editedCourseData);

            $client->request(
                'post',
                "/api/v1/courses/$actualCode",
                [],
                [],
                $this->getHeaders($adminAccessToken),
                $serializedCourse
            );

            self::assertJsonResponse($client->getResponse());

            if (array_key_exists('code', $editedCourseData)) {
                $actualCode = $editedCourseData['code'];
            }

            $client->request(
                'get',
                "/api/v1/courses/$actualCode",
                [],
                [],
                $this->getHeaders($adminAccessToken)
            );

            self::assertJsonResponse($client->getResponse());

            $receivedCourse = json_decode($client->getResponse()->getContent(), true);

            $courseCorrect = $editedCourseData['code'] === $receivedCourse['code']
                && $editedCourseData['type'] === $receivedCourse['type']
                && $editedCourseData['title'] === $receivedCourse['title'];

            if (array_key_exists('price', $editedCourseData)) {
                $courseCorrect = $courseCorrect && $editedCourseData['price'] === $receivedCourse['price'];
            }

            if (array_key_exists('rent_time', $editedCourseData)) {
                $courseCorrect = $courseCorrect && $editedCourseData['rent_time'] === $receivedCourse['rent_time'];
            }

            self::assertTrue($courseCorrect);
        }
    }

    public function testIncorrectEditCourse()
    {
        $client = self::getClient();
        $adminAccessToken = $this->performAuthorization(true);

        $course = [
            'code' => 'test_course',
            'type' => 'rent',
            'title' => 'Тестовый курс №100',
            'price' => 2349.01,
            'rent_time' => 'P25D',
        ];

        $serializerData = json_encode($course);

        $client->request(
            'post',
            '/api/v1/courses/',
            [],
            [],
            $this->getHeaders($adminAccessToken),
            $serializerData
        );

        self::assertJsonResponse($client->getResponse(), 201);

        $code = $course['code'];

        for ($i = 0; $i != count($this->incorrectCourseData); ++$i) {
            $serializedData = json_encode($this->incorrectCourseData[$i]);

            $client->request(
                'post',
                "/api/v1/courses/$code",
                [],
                [],
                $this->getHeaders($adminAccessToken),
                $serializedData
            );

            self::assertJsonResponse($client->getResponse(), 400);

            $response = json_decode($client->getResponse()->getContent(), true);

            $errors = $response['details'];

            self::assertEquals(count($errors), count($this->incorrectMessages[$i]));

            foreach ($this->incorrectMessages[$i] as $message) {
                $exists = in_array($message, $errors);

                self::assertTrue($exists);
            }
        }
    }

    public function testDeleteCourse()
    {
        $client = self::getClient();
        $adminAccessToken = $this->performAuthorization(true);

        $course = [
            'code' => 'test_course',
            'type' => 'rent',
            'title' => 'Тестовый курс №100',
            'price' => 2349.01,
            'rent_time' => 'P25D',
        ];

        $serializerData = json_encode($course);

        $client->request(
            'post',
            '/api/v1/courses/',
            [],
            [],
            $this->getHeaders($adminAccessToken),
            $serializerData
        );

        self::assertJsonResponse($client->getResponse(), 201);

        $client->request(
            'delete',
            "/api/v1/courses/{$course['code']}",
            [],
            [],
            $this->getHeaders($adminAccessToken)
        );

        $foundedCourse = $this->entityManager->getRepository(Course::class)
            ->findOneBy(['code' => $course['code'], 'active' => true]);

        self::assertNull($foundedCourse);
    }

    public function testCreateCourseAsNonAdmin()
    {
        $client = self::getClient();
        $accessToken = $this->performAuthorization(false);

        $course = [
            'code' => 'test_course',
            'type' => 'rent',
            'title' => 'Тестовый курс №100',
            'price' => 2349.01,
            'rent_time' => 'P25D',
        ];

        $serializerData = json_encode($course);
        $client->request(
            'post',
            '/api/v1/courses/',
            [],
            [],
            $this->getHeaders($accessToken),
            $serializerData
        );

        self::assertEquals(403, $client->getResponse()->getStatusCode());
    }

    public function testEditCourseAsNonAdmin()
    {
        $client = self::getClient();
        $userAccessToken = $this->performAuthorization(false);
        $adminAccessToken = $this->performAuthorization(true);

        $course = [
            'code' => 'test_course',
            'type' => 'rent',
            'title' => 'Тестовый курс №100',
            'price' => 2349.01,
            'rent_time' => 'P25D',
        ];

        $editedCourse = [
            'code' => 'тестовый_курс_3',
            'type' => 'buy',
            'title' => 'Тестовый курс №3',
            'price' => 123.45,
        ];

        $serializedData = json_encode($course);
        $client->request(
            'post',
            '/api/v1/courses/',
            [],
            [],
            $this->getHeaders($adminAccessToken),
            $serializedData
        );

        $this->assertJsonResponse($client->getResponse(), 201);

        $serializedData = json_encode($editedCourse);
        $client->request(
            'post',
            "/api/v1/courses/{$course['code']}",
            [],
            [],
            $this->getHeaders($userAccessToken),
            $serializedData
        );

        self::assertEquals(403, $client->getResponse()->getStatusCode());
    }

    public function testDeleteCourseAsNonAdmin()
    {
        $client = self::getClient();
        $userAccessToken = $this->performAuthorization(false);
        $adminAccessToken = $this->performAuthorization(true);

        $course = [
            'code' => 'test_course',
            'type' => 'rent',
            'title' => 'Тестовый курс №100',
            'price' => 2349.01,
            'rent_time' => 'P25D',
        ];

        $serializedData = json_encode($course);
        $client->request(
            'post',
            '/api/v1/courses/',
            [],
            [],
            $this->getHeaders($adminAccessToken),
            $serializedData
        );

        $this->assertJsonResponse($client->getResponse(), 201);

        $client->request(
            'delete',
            "/api/v1/courses/{$course['code']}",
            [],
            [],
            $this->getHeaders($userAccessToken)
        );

        self::assertEquals(403, $client->getResponse()->getStatusCode());
    }

    public function testUnauthorizedCoursesList()
    {
        $client = self::getClient();

        $client->request(
            'get',
            '/api/v1/u/courses',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json']
        );

        self::assertJsonResponse($client->getResponse());

        $data = json_decode($client->getResponse()->getContent(), true);

        $courses = $this->entityManager->getRepository(Course::class)->findBy(['active' => true]);

        self::assertEquals(count($data), count($courses));
    }

    public function testUnauthorizedGetCourse()
    {
        $client = self::getClient();

        $adminAccessToken = $this->performAuthorization(true);

        // Создание курса от админа

        $course = [
            'code' => 'test_course',
            'type' => 'rent',
            'title' => 'Тестовый курс №100',
            'price' => 2349.01,
            'rent_time' => 'P25D',
        ];

        $serializedData = json_encode($course);
        $client->request(
            'post',
            '/api/v1/courses/',
            [],
            [],
            $this->getHeaders($adminAccessToken),
            $serializedData
        );

        $this->assertJsonResponse($client->getResponse(), 201);

        // Получение курса
        $client->request(
            'get',
            "/api/v1/u/courses/{$course['code']}",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json']
        );

        self::assertJsonResponse($client->getResponse());

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertTrue(
            $data['code'] === $course['code']
            && $data['type'] === $course['type']
            && $data['title'] === $course['title']
            && $data['price'] === $course['price']
            && $data['rent_time'] === $course['rent_time']
        );
    }
}
