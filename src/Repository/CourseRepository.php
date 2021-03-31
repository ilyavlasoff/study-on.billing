<?php

namespace App\Repository;

use App\Entity\Course;
use App\Entity\User;
use App\Model\OwnedCourseDto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
            select case when course.type in (0, 2) then true else false end inf, valid_until as valid
            from course join transaction on course.id = transaction.course_id
            join billing_user on transaction.user_id = billing_user.id
            where billing_user.id = :usrId
            and course.id = :courseId
            and operation_type = 0
            and created_at < current_date
            and (course.type in (0, 2) or (course.type = 1 and valid_until > current_date));
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

    /**
     * @param User $user
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getCoursesList(User $user): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            with user_owning as (
                select course.id as cid, course.type as ctype, valid_until as vu
                from course join transaction on course.id = transaction.course_id
                            join billing_user on transaction.user_id = billing_user.id
                where billing_user.id = :userId
                  and operation_type = 0
                  and created_at < current_date
                  and (course.type in (0, 2) or (course.type = 1 and valid_until > current_date))
            )
            select id,  code, type, title, cost, (
                select case when count(cid) > 0 then true else false end owns from user_owning where cid = course.id
            ),(
                select max(vu) as until from user_owning where cid = course.id
            ) from course;
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
            $ownedCourse = new OwnedCourseDto($course);
            $ownedCourse->setOwned($courseRow['owns']);
            $ownedCourse->setOwnedUntil($courseRow['until'] ? new \DateTime($courseRow['until']) : null);
            $courses[] = $ownedCourse;
        }

        return $courses;
    }
}
