<?php

namespace App\Repository;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;
use function Doctrine\ORM\QueryBuilder;

/**
 * @method Transaction|null find($id, $lockMode = null, $lockVersion = null)
 * @method Transaction|null findOneBy(array $criteria, array $orderBy = null)
 * @method Transaction[]    findAll()
 * @method Transaction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    public function filterUserTransactions(User $user, $type, $course, $skipExpired)
    {
        $queryBuilder = $this->createQueryBuilder('tr')
            ->where('tr.user = :user')
            ->setParameter('user', $user);

        if ($type && $type instanceof Transaction) {
            $queryBuilder->andWhere('tr.operationType = :optype')
                ->setParameter('optype', $type->getOperationType());
        }

        if ($course) {
            $queryBuilder->andWhere('tr.course = :courseCode')
                ->setParameter('courseCode', $course);
        }

        if ($skipExpired) {
            $queryBuilder->andWhere('tr.validUntil is null or tr.validUntil >= :validDate')
                ->setParameter('validDate', new \DateTime());
        }

        return $queryBuilder->getQuery()->getResult();
    }

    public function getEndingCourses()
    {
        $qb = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $qb
            ->select('c2.title, c2.email, c2.valid_until')
            ->from(
                sprintf(
                    '(%s)',
                    $this->getEntityManager()->getConnection()->createQueryBuilder()
                        ->select('c.id, c.title, bu.email, tr.valid_until,
               row_number() over (partition by bu.id, c.id order by tr.valid_until desc) as n')
                        ->from('transaction', 'tr')
                        ->innerJoin('tr', 'course', 'c', 'c.id = tr.course_id')
                        ->innerJoin('tr', 'billing_user', 'bu', 'bu.id = tr.user_id')
                        ->where('c.type = :rent_type')
                        ->getSQL()
                ),
                'c2'
            )
            ->where("date(c2.valid_until) = date(current_date + interval '2 days')")
            ->andWhere('c2.n = 1')
            ->orderBy('c2.valid_until')
            ->setParameter('rent_type', 1);
        $result = $qb->execute();
        $data = $result->fetchAll();

        return $data;
    }

    public function getCourseStats(\DateTime $fromDate, \DateTime $toDate)
    {
        $qb = $this->createQueryBuilder('t');

        $stats = $qb->select('c.title as name, c.type, count(t.id) as buy_count, sum(t.value) as money_sum')
            ->innerJoin(Course::class, 'c', Join::WITH, 'c.id = t.course')
            ->where($qb->expr()->between('t.createdAt', ':from', ':to'))
            ->groupBy('c.title, c.type')
            ->orderBy('sum(t.value)', 'desc')
            ->setParameter('from', $fromDate)
            ->setParameter('to', $toDate)
            ->getQuery()
            ->getResult();

        return $stats;
    }

    public function getSumEarned(\DateTime $fromDate, \DateTime $toDate)
    {
        $qb = $this->createQueryBuilder('tr');

        $sumEarned = $qb
            ->select('sum(tr.value) as money_sum')
            ->where($qb->expr()->between('tr.createdAt', ':from', ':to'))
            ->setParameter('from', $fromDate)
            ->setParameter('to', $toDate)
            ->getQuery()->getResult();

        return $sumEarned[0]['money_sum'];
    }
}
