<?php

namespace App\Tests;

use App\Entity\Transaction;
use PHPUnit\Framework\Assert;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Mime\RawMessage;

class CommandTest extends WebTestCase
{
    private $entityManager;

    protected function setUp(): void
    {
        static::bootKernel();
        $this->entityManager = self::$container->get('doctrine.orm.entity_manager');
    }

    public function testSendingReportMessages()
    {
        $kernel = static::bootKernel();
        $application = new Application($kernel);

        $command = $application->find('payment:report');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        self::assertEmailCount(1);
        $message = $this->getMailerMessage(0, 'smtp://mailhog:1025');
        self::assertNotNull($message);
        self::assertEquals(TemplatedEmail::class, get_class($message));

        $mailRecipients = $message->getTo();
        self::assertNotEmpty($mailRecipients);
        $address = ($mailRecipients[0])->getAddress();
        self::assertEquals('analytic@study-on.local', $address);

        $mailBody = $message->getHtmlBody();

        $courseTypeTranslate = [
            '0' => 'Бесплатный',
            '1' => 'Арендуемый',
            '2' => 'Приобретаемый',
        ];

        $currentDate = new \DateTime();
        $lastMonthDate = (new \DateTime())->modify('-1 month');

        $courseStats = $this->entityManager->getRepository(Transaction::class)
            ->getCourseStats($lastMonthDate, $currentDate);

        $document = new \DOMDocument();
        $document->loadHTML($mailBody);
        $crawler = new Crawler($document);

        $emailCoursesList = $crawler->filter('table .content tr');
        self::assertEquals(count($courseStats), $emailCoursesList->count());

        for ($i = 0; $i != count($courseStats); ++$i) {
            $row = $emailCoursesList->eq($i)->filter('td');

            self::assertEquals(4, $row->count());

            $nameContent = $row->eq(0)->text();
            self::assertEquals($courseStats[$i]['name'], $nameContent);

            $typeContent = $row->eq(1)->text();
            self::assertEquals($courseTypeTranslate[$courseStats[$i]['type']], $typeContent);

            $buyContent = $row->eq(2)->text();
            self::assertEquals($courseStats[$i]['buy_count'], $buyContent);

            $moneyContent = $row->eq(3)->text();
            $roundedPrice = floor($courseStats[$i]['money_sum'] * 100) / 100;
            self::assertEquals("$roundedPrice руб.", $moneyContent);
        }
    }

    public function testSendingEndingCourseRentMessages()
    {
        $kernel = static::bootKernel();
        $application = new Application($kernel);

        $command = $application->find('payment:ending:notification');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        /** @var \App\Repository\TransactionRepository $tr */
        $tr = $this->entityManager->getRepository(Transaction::class);

        $endingCoursesList = $tr->getEndingCourses();

        $sentMessages = [];
        foreach ($endingCoursesList as $course) {
            $sentMessages[$course['email']][] = ['course' => $course['title'], 'timeUntil' => $course['valid_until']];
        }

        self::assertEmailCount(count($sentMessages));

        /** @var TemplatedEmail[] $messages */
        $messages = $this->getMailerMessages('smtp://mailhog:1025');

        foreach ($messages as $receivedMessage) {
            self::assertEquals(TemplatedEmail::class, get_class($receivedMessage));

            $mailRecipients = $receivedMessage->getTo();

            self::assertNotEmpty($mailRecipients);
            $address = ($mailRecipients[0])->getAddress();
            self::assertNotNull($sentMessages[$address]);
            $sentMessageData = $sentMessages[$address];

            $mailBody = $receivedMessage->getHtmlBody();

            $document = new \DOMDocument();
            $document->loadHTML($mailBody);
            $crawler = new Crawler($document);

            $emailCoursesList = $crawler->filter('li');
            self::assertEquals(count($sentMessageData), count($emailCoursesList));

            for ($j = 0; $j != count($sentMessageData); ++$j) {
                $liText = $emailCoursesList->eq($j)->text();
                $expectedTime = date_format(new \DateTime($sentMessageData[$j]['timeUntil']), 'd.m.Y H:i');
                $expectedString = "{$sentMessageData[$j]['course']} - $expectedTime";
                self::assertEquals($expectedString, $liText);
            }
        }
    }
}
