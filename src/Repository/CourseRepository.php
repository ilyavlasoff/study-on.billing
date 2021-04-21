<?php

namespace App\Repository;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use App\Model\Response\OwnedCourseDto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Course|null find($id, $lockMode = null, $lockVersion = null)
 * @method Course|null findOneBy(array $criteria, array $orderBy = null)
 * @method Course[]    findAll()
 * @method Course[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CourseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Course::class);
    }

    /**
     * @param User $user
     * @param Course $course
     * @return mixed|null
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getUserOwning(User $user, Course $course)
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            select case when course.type <> 1 then true else false end inf, valid_until as valid
            from course join transaction on course.id = transaction.course_id
            join billing_user on transaction.user_id = billing_user.id
            where billing_user.id = :usrId
            and course.id = :courseId
            and operation_type = 0
            and created_at < now()
            and (course.type <> 1 or (course.type = 1 and valid_until > now()));
        ';

        $stmt = $conn->prepare($sql);
        $stmt->bindValue('usrId', $user->getId());
        $stmt->bindValue('courseId', $course->getId());
        $stmt->execute();
        $queryResult = $stmt->fetchAll();

        if (!count($queryResult)) {
            return null;
        }

        return $queryResult[0];
    }

    public function getValidCoursesForUser(User $user)
    {
        $qb = $this->createQueryBuilder('c');
        $courses = $qb
            ->select('c')
            ->distinct()
            ->join(Transaction::class, 'tr', Join::WITH, 'c.id = tr.course')
            ->where('tr.user = :user')
            ->andWhere('tr.createdAt <= :now')
            ->andWhere(
                $qb->expr()->orX(
                    'c.type <> 1',
                    $qb->expr()->andX('c.type = 1', 'tr.validUntil > :now')
                )
            )
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();

        return $courses;
    }

    /**
     * @param User $user
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getCoursesList(User $user): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
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

        $stmt = $conn->prepare($sql);
        $stmt->bindValue('userId', $user->getId());
        $stmt->execute();

        $courses = [];

        while ($courseRow = $stmt->fetch()) {
            $course = new Course();
            $course->setCode($courseRow['code']);
            $course->setType($courseRow['type']);
            $course->setTitle($courseRow['title']);
            $course->setCost($courseRow['cost']);
            $course->setRentTime(null);
            $ownedCourse = new OwnedCourseDto($course);
            $ownedCourse->setOwned($courseRow['owns']);
            $ownedCourse->setOwnedUntil($courseRow['until'] ? new \DateTime($courseRow['until']) : null);
            $courses[] = $ownedCourse;
        }

        return $courses;
    }
}
