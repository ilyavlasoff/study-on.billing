<?php

namespace App\Service;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class PaymentService
{
    private $entityManager;
    private $connection;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->connection = $this->entityManager->getConnection();
    }

    public function billingDeposit(UserInterface $user, float $amount): void
    {
        $this->connection->beginTransaction();
        try {
            $transaction = new Transaction();
            $transaction->setValue($amount);
            $transaction->setCreatedAt(new \DateTime());
            $transaction->setStringOperationType('deposit');
            $transaction->setUser($user);
            $this->entityManager->persist($transaction);
            $user->setBalance($user->getBalance() + $amount);
            $this->entityManager->flush();
            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    public function coursePayment(UserInterface $user, Course $course): Transaction
    {
        $this->connection->beginTransaction();
        try {
            if ($user->getBalance() < $course->getCost()) {
                throw new \Exception('Cash not enough');
            }
            $transaction = new Transaction();
            $transaction->setUser($user);
            $transaction->setCourse($course);
            $transaction->setCreatedAt(new \DateTime());
            $transaction->setStringOperationType('payment');
            $transaction->setValue($course->getCost());
            if ($course->getStringType() === 'rent') {
                $transaction->setValidUntil((new \DateTime())->add($course->getRentTime()));
            }
            $this->entityManager->persist($transaction);
            $user->setBalance($user->getBalance() - $course->getCost());
            $this->entityManager->flush();
            $this->connection->commit();
            return $transaction;
        } catch (\Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }
}
