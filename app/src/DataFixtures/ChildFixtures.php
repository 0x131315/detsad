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
    protected \Faker\Generator $faker;

    public function __construct()
    {
        $this->faker = Factory::create('ru_RU');
    }

    public function load(ObjectManager $manager)
    {
        $faker = $this->faker;
        $groups = $manager->getRepository(KindGroup::class)->findAll();
        foreach ($groups as $group) {
            $totalChilds = $this->faker->numberBetween(5, 20);
            $genderChance = $this->faker->numberBetween(30, 70);
            while ($totalChilds-- > 0) {
                $child = $this->buildChild($genderChance);
                $child->setKindGroup($group);
                $child->setIsPresent($faker->optional(0.98)->boolean(95));

                $manager->persist($child);
            }
        }

        $totalProblemChilds = $faker->numberBetween(1, 3);
        $genderChance = $faker->numberBetween(30, 70);
        while ($totalProblemChilds-- > 0) {
            $child = $this->buildChild($genderChance);
            $manager->persist($child);
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

    protected function buildChild(int $genderChance,): Child
    {
        $faker = $this->faker;
        $child = new Child();
        $child->setGender($faker->boolean($genderChance) ? 'male' : 'female');
        $child->setFirstName($faker->firstName($child->getGender()));
        $child->setLastName($faker->lastName($child->getGender()));

        return $child;
    }
}
