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

    public function getValidCoursesForUser(User $user)
    {
        $qb = $this->createQueryBuilder('tr');
        $courses = $qb->select('c')
            ->join(Course::class, 'c', Join::WITH, 'c.id = tr.course')
            ->where('tr.user = :user')
            ->andWhere('tr.operationType = :optype')
            ->andWhere('tr.createdAt <= :now')
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->in('c.type', [0, 2]),
                    $qb->expr()->andX('c.type = :renttype', 'tr.validUntil > :now')
                )
            )
            ->setParameter('user', $user)
            ->setParameter('optype', 0)
            ->setParameter('now', new \DateTime())
            ->setParameter('renttype', 1)
            ->getQuery()->getResult();

        return $courses;
    }

}
