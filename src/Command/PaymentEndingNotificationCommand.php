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

class PaymentEndingNotificationCommand extends Command
{
    protected static $defaultName = 'payment:ending:notification';

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
        $this->setDescription('Send notification about payment ending');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var \App\Repository\TransactionRepository $tr */
        $tr = $this->entityManager->getRepository(Transaction::class);
        $endingCoursesData = $tr->getEndingCourses(new \DateInterval('P1D'));

        $endingCourses = [];
        foreach ($endingCoursesData as $course) {
            $endingCourses[$course['email']] = ['course' => $course['title'], 'timeUntil' => $course['valid_until']];
        }

        foreach ($endingCourses as $ownerEmail => $endingCourse) {
            $email = (new TemplatedEmail())
                ->from($this->parameterBag->get('send_from'))
                ->to($ownerEmail)
                ->subject('Окончание аренды курсов Study-On')
                ->htmlTemplate('payment_ending_notification.html.twig')
                ->context([
                    'notifications' => $endingCourse
                ]);

            try {
                $this->mailer->send($email);
            } catch (TransportExceptionInterface $e) {
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }
}
