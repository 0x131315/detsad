<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class UserFixtures extends Fixture
{
    public function __construct(private UserPasswordEncoderInterface $passwordEncoder)
    {
    }

    public function load(ObjectManager $manager)
    {
        $manager->persist($this->buildUser('admin@example.org', 'admin', 'ROLE_ADMIN'));
        $manager->flush();
    }

    protected function buildUser(string $email, string $password, $roles): User
    {
        $user = new User();
        $user->setRoles(array_unique(array_merge(['ROLE_USER'], (array)$roles)));
        $user->setEmail($email);
        $this->setPassword($user, $password);
        return $user;
    }

    protected function setPassword(User $user, string $password): void
    {
        $encoded = $this->passwordEncoder->encodePassword($user, $password);
        $user->setPassword($encoded);
    }
}
