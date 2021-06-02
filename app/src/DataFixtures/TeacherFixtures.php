<?php

namespace App\DataFixtures;

use App\Entity\Child;
use App\Entity\KindGroup;
use App\Entity\Teacher;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class TeacherFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(private UserPasswordEncoderInterface $passwordEncoder)
    {
    }

    public function load(ObjectManager $manager)
    {
        $faker = Factory::create('ru_RU');
        $groups = $manager->getRepository(KindGroup::class)->findAll();
        $teacherCount = 0;
        foreach ($groups as $group) {
            $totalTeachers = $faker->numberBetween(1, 2);
            while ($totalTeachers-- > 0) {
                $teacherCount++;

                $email = 'teacher' . $teacherCount . '@example.org';
                $user = $this->buildUser($email, 'user', 'ROLE_TEACHER');
                $manager->persist($user);

                $teacher = new Teacher();
                $teacher->setUser($user);
                $teacher->setKindGroup($group);
                $teacher->setFirstName($faker->firstName('female'));
                $teacher->setLastName($faker->lastName('female'));

                $manager->persist($teacher);
            }
        }

        $manager->flush();
    }

    /**
     * @inheritDoc
     */
    public function getDependencies()
    {
        return [
            GroupFixtures::class,
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

    protected function setPassword(User $user, string $password)
    {
        $encoded = $this->passwordEncoder->encodePassword($user, $password);
        $user->setPassword($encoded);
    }
}
