<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class GroupFixtures extends Fixture
{
    const names = [
        'Смешарики', 'Цыплята', 'Яблочко', 'Лучик', 'Мячик', 'Капельки', 'Звёздочки', 'Колыбелька',
        'Сказка', 'Муравейник', 'Фейерверк', 'Карусель', 'Дельфины', 'Лимпопо', 'Пластилин', 'Радость', 'Островок'];

    public function load(ObjectManager $manager)
    {
        $faker = Factory::create('ru_RU');
        $totalGroups = $faker->numberBetween(5, count(self::names));
        while ($totalGroups-- > 0) {
            $group = new \App\Entity\Group();
            $group->setName($faker->unique()->randomElement(self::names));
            $manager->persist($group);
        }

        $manager->flush();
    }
}
