<?php

namespace App\DataFixtures;

use App\Entity\Brand;
use App\Entity\Ride;
use App\Entity\User;
use App\Entity\Vehicle;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $em): void
    {
        // 1) Utilisateur “driver”
        $u = (new User())
            ->setEmail('driver@ecoride.test')
            ->setPassword(password_hash('password', PASSWORD_BCRYPT))
            ->setPseudo('ecoDriver')
            ->setPhone('0600000000')
            ->setCreditsBalance(100);

        // 2) Marque + véhicule
        $b = (new Brand())->setName('Tesla');
        $v = (new Vehicle())
            ->setOwner($u)
            ->setBrand($b)
            ->setPlate('AB-123-CD')
            ->setModel('Model 3')
            ->setEnergy('electric')
            ->setSeatsTotal(4)
            ->setColor('white')
            ->setEco(true)
            ->setFirstRegistrationAt(new \DateTimeImmutable('2022-05-01'));

        // 3) 2 trajets de démo
        $r1 = (new Ride())
            ->setDriver($u)
            ->setVehicle($v)
            ->setFromCity('Paris')
            ->setFromLat('48.8566140')
            ->setFromLng('2.3522219')
            ->setToCity('Lille')
            ->setToLat('50.6292500')
            ->setToLng('3.0572560')
            ->setStartAt(new \DateTimeImmutable('tomorrow 09:00'))
            ->setEndAt(new \DateTimeImmutable('tomorrow 11:30'))
            ->setPrice('15.00')
            ->setSeatsTotal(4)
            ->setSeatsLeft(3)
            ->setStatus('open')
            ->setAllowSmoker(false)
            ->setAllowAnimals(false);

        $r2 = (new Ride())
            ->setDriver($u)
            ->setVehicle($v)
            ->setFromCity('Paris')
            ->setFromLat('48.8566140')
            ->setFromLng('2.3522219')
            ->setToCity('Lille')
            ->setToLat('50.6292500')
            ->setToLng('3.0572560')
            ->setStartAt(new \DateTimeImmutable('+3 days 08:00'))
            ->setEndAt(new \DateTimeImmutable('+3 days 10:30'))
            ->setPrice('18.00')
            ->setSeatsTotal(4)
            ->setSeatsLeft(2)
            ->setStatus('open')
            ->setAllowSmoker(false)
            ->setAllowAnimals(true);

        // Persist
        $em->persist($u);
        $em->persist($b);
        $em->persist($v);
        $em->persist($r1);
        $em->persist($r2);

        $em->flush();
    }
}
