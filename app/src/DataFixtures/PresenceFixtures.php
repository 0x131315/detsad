<?php

namespace App\DataFixtures;

use App\Entity\Child;
use App\Entity\KindGroup;
use App\Entity\PresenceHistory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;

class PresenceFixtures extends Fixture implements DependentFixtureInterface
{
    protected const weekendDays = ['Sat', 'Sun'];
    protected Generator $faker;

    public function __construct()
    {
        $this->faker = Factory::create('ru_RU');
    }

    public function load(ObjectManager $manager)
    {
        $this->fillHistory($manager, '-12 months');
        $this->fillToday($manager);

        $manager->flush();
    }

    protected function fillToday(ObjectManager $manager)
    {
        $childs = $manager->getRepository(Child::class)->findAll();
        foreach ($childs as $child) {
            $presence = new PresenceHistory();
            $presence->setChild($child);
            $presence->setPresence($child->getIsPresent());

            $manager->persist($presence);
        }
    }

    protected function fillHistory(ObjectManager $manager, string $from): void
    {
        $today = (new \DateTime())->setTime(0, 0);
        $dateRange = new \DatePeriod(
            date_create($from),
            //$this->faker->dateTimeBetween($from)->setTime(0, 0),
            \DateInterval::createFromDateString('1 day'),
            $today
        );

        $childs = $manager->getRepository(Child::class)->findAll();
        $groups = $manager->getRepository(KindGroup::class)->findAll();

        $seeds = [];
        foreach (range(1, 12) as $month) {
            $seeds['monthy'][$month] = random_int(0, 10);
        }
        foreach ($groups as $group) {
            $seeds['group'][$group->getId()] = random_int(0, 10);
        }
        foreach ($childs as $child) {
            $seeds['child'][$child->getId()] = random_int(0, 10);
        }

        foreach ($dateRange as $date) {
            $isWeekendDay = in_array($date->format('D'), self::weekendDays);
            if ($isWeekendDay) {
                continue;
            }

            foreach ($childs as $child) {
                $seed = $seeds['child'][$child->getId()] + $seeds['monthy'][(int)$date->format('m')];
                $seed += $child->getKindGroup() ? $seeds['group'][$child->getKindGroup()->getId()] : 0;

                $presence = new PresenceHistory();
                $presence->setDate($date);
                $presence->setChild($child);
                $presence->setPresence($this->faker->optional(0.98)->boolean(70 + $seed));

                $manager->persist($presence);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function getDependencies()
    {
        return [
            ChildFixtures::class
        ];
    }
}
