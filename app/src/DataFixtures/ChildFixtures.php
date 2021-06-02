<?php

namespace App\DataFixtures;

use App\Entity\Child;
use App\Entity\KindGroup;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class ChildFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager)
    {
        $faker = Factory::create('ru_RU');
        $groups = $manager->getRepository(KindGroup::class)->findAll();
        foreach ($groups as $group) {
            $totalChilds = $faker->numberBetween(5, 20);
            $groupGender = $faker->numberBetween(30, 70);
            while ($totalChilds-- > 0) {
                $child = new Child();
                $child->setKindGroup($group);
                $child->setGender($faker->boolean($groupGender) ? 'male' : 'female');
                $child->setFirstName($faker->firstName($child->getGender()));
                $child->setLastName($faker->lastName($child->getGender()));
                $child->setIsPresent($faker->optional(0.98)->boolean(95));

                $manager->persist($child);
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
            GroupFixtures::class
        ];
    }
}
