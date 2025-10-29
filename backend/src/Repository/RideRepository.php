<?php

namespace App\Repository;

use App\Entity\Ride;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ride>
 */
class RideRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ride::class);
    }

    //    /**
    //     * @return Ride[] Returns an array of Ride objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('r.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Ride
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
    public function searchAvailable(string $from, string $to, \DateTimeInterface $date): array
{
    $qb = $this->createQueryBuilder('r')
        ->andWhere('r.seatsLeft > 0')
        ->andWhere('r.status = :open')->setParameter('open', 'open')
        ->andWhere('r.fromCity LIKE :from')->setParameter('from', $from.'%')
        ->andWhere('r.toCity LIKE :to')->setParameter('to', $to.'%')
        ->andWhere('r.startAt BETWEEN :d1 AND :d2')
        ->setParameter('d1', (new \DateTimeImmutable($date->format('Y-m-d').' 00:00:00')))
        ->setParameter('d2', (new \DateTimeImmutable($date->format('Y-m-d').' 23:59:59')))
        ->orderBy('r.startAt', 'ASC');

    return $qb->getQuery()->getResult();
}

}
