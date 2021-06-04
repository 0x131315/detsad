<?php

namespace App\DataFixtures;

use App\Entity\KindGroup;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class GroupFixtures extends Fixture
{
    protected const names = [
        'Смешарики', 'Цыплята', 'Яблочко', 'Лучик', 'Мячик', 'Капельки', 'Звёздочки', 'Колыбелька',
        'Сказка', 'Муравейник', 'Фейерверк', 'Карусель', 'Дельфины', 'Лимпопо', 'Пластилин', 'Радость', 'Островок'];

    public function load(ObjectManager $manager)
    {
        $faker = Factory::create('ru_RU');
        $totalGroups = $faker->numberBetween(5, count(self::names));
        while ($totalGroups-- > 0) {
            $group = new KindGroup();
            $group->setName($faker->unique()->randomElement(self::names));
            $manager->persist($group);
        }

        $manager->flush();
    }
}
