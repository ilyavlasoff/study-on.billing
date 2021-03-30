<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class UserFixtures extends Fixture
{
    private $passwordEncoder;

    public function __construct(UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->passwordEncoder = $passwordEncoder;
    }

    public function load(ObjectManager $manager)
    {
        $user = new User();
        $user->setEmail('user@test.com');
        $userEncodedPassword = $this->passwordEncoder->encodePassword($user, 'passwd');
        $user->setPassword($userEncodedPassword);
        $user->setRoles(['ROLE_USER']);
        $user->setBalance(0);
        $manager->persist($user);

        $admin = new User();
        $admin->setEmail('admin@test.com');
        $adminEncodedPassword = $this->passwordEncoder->encodePassword($admin, 'passwd');
        $admin->setPassword($adminEncodedPassword);
        $admin->setRoles(['ROLE_SUPER_ADMIN']);
        $admin->setBalance(1000);
        $manager->persist($admin);

        $courseNames = [
            'Проектирование конструкций зданий в Autodesk Revit',
            'Программирование на Golang',
            'Цифровизация образовательного процесса в школаx',
            'Лидерство и командообразование',
            'Цифровые инструменты и сервисы для учителя',
            'Алгоритмы: теория и практика. Методы',
            'Социальное проектирование в НКО',
            'Постановка задачи на разработку ПО',
            'Программирование на C и выполнение программ',
            'Базовые навыки Excel',
            'Введение в Linux',
            'Секреты хороших текстов'
        ];

        $courses = [];
        foreach ($courseNames as $courseName) {
            $course = new Course();
            $course->setTitle($courseName);
            $course->setCode('course_' . mb_strtolower(str_replace(' ', '_', $courseName)));
            $course->setType(random_int(0, 2));
            $course->setCost($course->getType() === Course::TYPE_FREE ? 0 : round(random_int(10000, 500000) / 10, 2));
            if ($course->getType() === Course::TYPE_RENT) {
                $course->setRentTime(new \DateInterval('P' . array_rand(array_flip([10, 30, 45, 60])) . 'D'));
            }
            $courses[] = $course;
            $manager->persist($course);
        }

        foreach ([$user, $admin] as $client) {
            $userCoursesCost = 0;
            $availableCourses = $courses;
            for ($i = 0; $i !== 10; ++$i) {
                $transaction = new Transaction();
                $transaction->setCreatedAt(new \DateTime());
                $transaction->setUser($client);
                if ($i < 7) {
                    $currentCourse = array_rand($availableCourses);
                    $transaction->setCourse($availableCourses[$currentCourse]);
                    array_splice($availableCourses, $currentCourse, 1);
                    $transaction->setStringOperationType('payment');
                    $cost = $transaction->getCourse()->getCost();
                    $userCoursesCost += $cost;
                    $transaction->setValue($cost);
                    if ($transaction->getCourse()->getType() === Course::TYPE_RENT) {
                        $transaction->setValidUntil((new \DateTime())->add($transaction->getCourse()->getRentTime()));
                    }
                } else {
                    $transaction->setStringOperationType('deposit');
                    $transaction->setValue($userCoursesCost / 3);
                }
                $manager->persist($transaction);
            }
        }

        $manager->flush();
    }
}
