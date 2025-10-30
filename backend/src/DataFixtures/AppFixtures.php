<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Brand;
use App\Entity\Vehicle;
use App\Entity\Ride;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $hasher) {}

    public function load(ObjectManager $em): void
    {
        // Utilisateur de test
        $u = (new User())
            ->setEmail('user1@mail.test')
            ->setPseudo('ecoDriver')
            ->setRoles(['ROLE_USER']);
        $u->setPassword($this->hasher->hashPassword($u, 'Passw0rd!'));
        $em->persist($u);

        // Tesla + Vehicle
        $b = (new Brand())->setName('Tesla'); $em->persist($b);
        $v = (new Vehicle())
            ->setOwner($u)->setBrand($b)->setModel('Model 3')
            ->setEnergy('electric')->setSeatsTotal(4)->setColor('white')
            ->setEco(true)->setFirstRegistrationAt(new \DateTimeImmutable('2023-01-01'));
        $em->persist($v);

        // Rides (Paris -> Lille)
        $r1 = (new Ride())
            ->setDriver($u)->setVehicle($v)
            ->setFromCity('Paris')->setFromLat('48.8566')->setFromLng('2.3522')
            ->setToCity('Lille')->setToLat('50.6292')->setToLng('3.0573')
            ->setStartAt(new \DateTimeImmutable('2025-10-30 09:00'))
            ->setEndAt(new \DateTimeImmutable('2025-10-30 11:30'))
            ->setPrice('15.00')->setSeatsTotal(4)->setSeatsLeft(3)
            ->setStatus('open')->setAllowSmoker(false)->setAllowAnimals(false)
            ->setCreatedAt(new \DateTimeImmutable('now'));
        $em->persist($r1);

        $r2 = (new Ride())
            ->setDriver($u)->setVehicle($v)
            ->setFromCity('Paris')->setFromLat('48.8566')->setFromLng('2.3522')
            ->setToCity('Lille')->setToLat('50.6292')->setToLng('3.0573')
            ->setStartAt(new \DateTimeImmutable('2025-11-01 08:00'))
            ->setEndAt(new \DateTimeImmutable('2025-11-01 10:30'))
            ->setPrice('18.00')->setSeatsTotal(4)->setSeatsLeft(2)
            ->setStatus('open')->setAllowSmoker(false)->setAllowAnimals(false)
            ->setCreatedAt(new \DateTimeImmutable('now'));
        $em->persist($r2);

        $em->flush();
    }
}
