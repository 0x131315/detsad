<?php

namespace App\DataFixtures;

use App\Entity\Manager;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class ManagerFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(private UserPasswordEncoderInterface $passwordEncoder)
    {
    }

    public function load(ObjectManager $manager)
    {
        $faker = Factory::create('ru_RU');
        $count = 0;
        $totalManager = $faker->numberBetween(2, 5);
        while ($totalManager-- > 0) {
            $count++;

            $email = 'manager' . $count . '@example.org';
            $user = $this->buildUser($email, 'user', 'ROLE_MANAGER');
            $manager->persist($user);

            $kindManager = new Manager();
            $kindManager->setUser($user);
            $kindManager->setFirstName($faker->firstName('female'));
            $kindManager->setLastName($faker->lastName('female'));

            $manager->persist($kindManager);
        }

        $manager->flush();
    }

    /**
     * @inheritDoc
     */
    public function getDependencies()
    {
        return [
            UserFixtures::class,
        ];
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
