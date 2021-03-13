<?php

namespace App\DataFixtures;

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

        $manager->flush();
    }
}
