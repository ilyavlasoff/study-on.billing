<?php

namespace App\Command;

use App\Entity\Transaction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;

class PaymentReportCommand extends Command
{
    protected static $defaultName = 'payment:report';

    private $entityManager;
    private $mailer;
    private $parameterBag;

    public function __construct(
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        ParameterBagInterface $parameterBag
    ) {
        $this->entityManager = $entityManager;
        $this->mailer = $mailer;
        $this->parameterBag = $parameterBag;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Notify about payments');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $courseTypeTranslate = [
            '0' => 'Бесплатный',
            '1' => 'Арендуемый',
            '2' => 'Покупаемый',
        ];

        $currentDate = new \DateTime();
        $lastMonthDate = $currentDate->modify('-1 month');

        /** @var \App\Repository\TransactionRepository $tr */
        $tr = $this->entityManager->getRepository(Transaction::class);

        $courseStats = $tr->getCourseStats($lastMonthDate, $currentDate);
        for ($i = 0; $i != count($courseStats); ++$i) {
            $typeCode = $courseStats[$i]['type'];
            if (array_key_exists($typeCode, $courseTypeTranslate)) {
                $courseStats[$i]['type'] = $courseTypeTranslate[$typeCode];
            }
        }

        $earnedSum = $tr->getSumEarned($lastMonthDate, $currentDate);

        $email = (new TemplatedEmail())
            ->from($this->parameterBag->get('send_from'))
            ->to($this->parameterBag->get('analytic_mail'))
            ->subject('Отчет о продажах за месяц')
            ->htmlTemplate('payment_report.html.twig')
            ->context([
                'period_start' => $lastMonthDate,
                'period_end' => $currentDate,
                'courses' => $courseStats,
                'total_sum' => $earnedSum
            ]);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
