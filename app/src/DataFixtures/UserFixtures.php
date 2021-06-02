<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class UserFixtures extends Fixture
{
    public function __construct(private UserPasswordEncoderInterface $passwordEncoder)
    {
    }

    public function load(ObjectManager $manager)
    {
        $faker = Factory::create('ru_RU');
        $totalUsers = $faker->numberBetween(10, 100);

        $manager->persist($this->buildUser('admin@admin.com', 'admin', 'ROLE_ADMIN'));
        while ($totalUsers-- > 0) {
            $email = $faker->unique()->safeEmail();
            $role = $faker->optional(0.1, 'ROLE_TEACHER')->passthrough('ROLE_MANAGER');
            $manager->persist($this->buildUser($email, 'user', $role));
        }

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

    protected function setPassword(User $user, string $password)
    {
        $encoded = $this->passwordEncoder->encodePassword($user, $password);
        $user->setPassword($encoded);
    }
}
