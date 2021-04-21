<?php

namespace App\Tests;

use App\DataFixtures\UserFixtures;
use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class PaymentTest extends AbstractTest
{
    private $accounts = [
        'user' => 'user@test.com',
        'admin' => 'admin@test.com',
    ];

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

    public function testGetUserData()
    {
        $client = self::getClient();
        $accessToken = $this->performAuthorization(false);

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $this->accounts['user']]);

        $client->request(
            'get',
            '/api/v1/users/current',
            [],
            [],
            $this->getHeaders($accessToken)
        );

        self::assertJsonResponse($client->getResponse());

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertEquals($user->getUsername(), $data['username']);
        self::assertEquals($user->getRoles(), $data['roles']);
        self::assertEquals($user->getBalance(), $data['balance']);
    }

    public function testPayment()
    {
        $client = self::getClient();
        $accessToken = $this->performAuthorization(false);
        $adminToken = $this->performAuthorization(true);

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $this->accounts['user']]);
        $oldBalance = $user->getBalance();

        $coursePrice = 0;

        if (0 == $user->getBalance()) {
            $course = [
                'code' => 'тестовый_курс_1',
                'type' => 'free',
                'title' => 'Тестовый курс №1',
            ];
        } else {
            $coursePrice = random_int(0, (int) $user->getBalance());

            $course = [
                'code' => 'тестовый_курс_1',
                'type' => 'rent',
                'title' => 'Тестовый курс №1',
                'price' => $coursePrice,
                'rent_time' => 'P10D',
            ];
        }

        $serializerData = json_encode($course);

        $client->request(
            'post',
            '/api/v1/courses/',
            [],
            [],
            $this->getHeaders($adminToken),
            $serializerData
        );

        self::assertJsonResponse($client->getResponse(), 201);

        $client->request(
            'post',
            "/api/v1/courses/{$course['code']}/pay",
            [],
            [],
            $this->getHeaders($accessToken)
        );

        self::assertJsonResponse($client->getResponse());

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertTrue($data['success']);
        self::assertEquals($course['type'], $data['course_type']);

        if ('rent' === $course['type']) {
            $expected = (new \DateTime('now'))->add(new \DateInterval($course['rent_time']));
            $received = new \DateTime($data['expires_at']);

            $expected->setTime($expected->format('H'), $expected->format('i'));
            $received->setTime($received->format('H'), $received->format('i'));
            self::assertEquals($expected, $received);
        }

        self::assertEquals($oldBalance - $coursePrice, $user->getBalance());

        $client->request(
            'get',
            "/api/v1/courses/{$course['code']}",
            [],
            [],
            $this->getHeaders($accessToken)
        );

        self::assertJsonResponse($client->getResponse());

        $receivedCourse = json_decode($client->getResponse()->getContent(), true);

        self::assertTrue($receivedCourse['owned']);
    }

    public function testIncorrectPayment()
    {
        $client = self::getClient();
        $accessToken = $this->performAuthorization(false);
        $adminToken = $this->performAuthorization(true);

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $this->accounts['user']]);

        // Проверка оплаты стоимости курса, большего значения баланса пользователя

        $course = [
            'code' => 'тестовый_курс_1',
            'type' => 'rent',
            'title' => 'Тестовый курс №1',
            'price' => $user->getBalance() + 1000,
            'rent_time' => 'P10D',
        ];

        $serializerData = json_encode($course);

        $client->request(
            'post',
            '/api/v1/courses/',
            [],
            [],
            $this->getHeaders($adminToken),
            $serializerData
        );

        self::assertJsonResponse($client->getResponse(), 201);

        $client->request(
            'post',
            "/api/v1/courses/{$course['code']}/pay",
            [],
            [],
            $this->getHeaders($accessToken)
        );

        self::assertEquals(406, $client->getResponse()->getStatusCode());

        // Проверка оплаты несуществуюшего курса

        $client->request(
            'post',
            '/api/v1/courses/undefined_course/pay',
            [],
            [],
            $this->getHeaders($accessToken)
        );

        self::assertEquals(404, $client->getResponse()->getStatusCode());
    }

    public function testGetTransactionsHistoryByType()
    {
        $client = self::getClient();
        $accessToken = $this->performAuthorization(false);

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $this->accounts['user']]);

        // Проверка по типам транзакций

        $opTypes = [
            'deposit' => 1,
            'payment' => 0,
        ];

        foreach (['deposit', 'payment'] as $transType) {
            $client->request(
                'get',
                "/api/v1/transactions?filter[type]=$transType",
                [],
                [],
                $this->getHeaders($accessToken)
            );

            self::assertJsonResponse($client->getResponse());

            $receivedCourse = json_decode($client->getResponse()->getContent(), true);

            $paidTransactions = $this->entityManager->getRepository(Transaction::class)
                ->findBy(['user' => $user->getId(), 'operationType' => $opTypes[$transType]]);

            self::assertEquals(count($receivedCourse), count($paidTransactions));

            foreach ($paidTransactions as $transaction) {
                $exists = array_filter($receivedCourse, function ($val) use ($transaction) {
                    return $val['id'] === $transaction->getId();
                });

                self::assertEquals(1, count($exists));
            }
        }
    }

    public function testGetTransactionsHistoryByCourse()
    {
        $client = self::getClient();
        $accessToken = $this->performAuthorization(false);
        $adminToken = $this->performAuthorization(true);

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $this->accounts['user']]);

        // Создание курса для покупки
        $course = [
            'code' => 'тестовый_курс_1',
            'type' => 'free',
            'title' => 'Тестовый курс №1',
        ];

        $serializerData = json_encode($course);

        $client->request(
            'post',
            '/api/v1/courses/',
            [],
            [],
            $this->getHeaders($adminToken),
            $serializerData
        );

        self::assertJsonResponse($client->getResponse(), 201);

        // Покупка курса для создания транзакции

        $client->request(
            'post',
            "/api/v1/courses/{$course['code']}/pay",
            [],
            [],
            $this->getHeaders($accessToken)
        );

        self::assertJsonResponse($client->getResponse());

        $client->request(
            'get',
            "/api/v1/transactions?filter[course_code]={$course['code']}",
            [],
            [],
            $this->getHeaders($accessToken)
        );

        self::assertJsonResponse($client->getResponse());

        $receivedTransaction = json_decode($client->getResponse()->getContent(), true);

        $courseEntity = $this->entityManager->getRepository(Course::class)->findOneBy(['code' => $course['code']]);

        // Поиск действительных транзакций

        $paidTransactions = $this->entityManager->getRepository(Transaction::class)
            ->findBy(['user' => $user->getId(), 'course' => $courseEntity->getId()]);

        self::assertEquals(count($receivedTransaction), count($paidTransactions));

        foreach ($paidTransactions as $transaction) {
            $exists = array_filter($receivedTransaction, function ($val) use ($transaction) {
                return $val['id'] === $transaction->getId();
            });

            self::assertEquals(1, count($exists));
        }
    }

    public function testGetTransactionsHistoryNotExpired()
    {
        $client = self::getClient();
        $accessToken = $this->performAuthorization(false);

        $client->request(
            'get',
            '/api/v1/transactions?filter[skip_expired]=1',
            [],
            [],
            $this->getHeaders($accessToken)
        );

        self::assertJsonResponse($client->getResponse());

        $receivedTransaction = json_decode($client->getResponse()->getContent(), true);

        $exp = array_filter($receivedTransaction, function ($value) {
            return array_key_exists('valid_until', $value)
                && (new \DateTime($value['valid_until']))->modify('+1 minute')
                < (new \DateTime('now', new \DateTimeZone('Europe/Moscow')));
        });
        if (count($exp) > 0) {
            var_dump($exp);
        }

        self::assertEquals(0, count($exp));
    }
}
