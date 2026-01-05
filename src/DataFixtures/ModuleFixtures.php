<?php

namespace App\DataFixtures;

use App\Entity\Module;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ModuleFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $modules = [
            ['Borne tactile', 'borne-tactile'],
            ['Menu digital', 'menu-digital'],
            ['Réservation', 'reservation'],
            ['Réputation', 'reputation'],
            ['Site web', 'siteweb'],
            ['Instagram', 'instagram'],
        ];

        foreach ($modules as [$name, $slug]) {
            $module = new Module();
            $module->setName($name);
            $module->setSlug($slug);
            $module->setActive(true);
            $module->setCreatedAt(new \DateTimeImmutable());

            $manager->persist($module);
        }

        $manager->flush();
    }
}

