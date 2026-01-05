<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        $admin = new User();
        $admin->setEmail('admin@digichef.fr');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setFirstName('Admin');
        $admin->setLastName('Digichef');
        $admin->setPassword(
            $this->passwordHasher->hashPassword($admin, 'admin123')
        );
        $admin->setCreatedAt(new \DateTimeImmutable());
        $admin->setUpdatedAt(new \DateTimeImmutable());

        $manager->persist($admin);
        $manager->flush();
    }
}
