<?php

namespace App\Controller;

use App\Entity\RideParticipant;
use App\Entity\User;
use App\Repository\RideParticipantRepository;
use App\Repository\RideRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api', name: 'api_user_')]
class UserController extends AbstractController
{
    #[Route('/users/{id}/bookings', name: 'bookings', methods: ['GET'])]
    public function bookings(
        int $id,
        EntityManagerInterface $em,
        UserRepository $users
    ): JsonResponse {
        /** @var User|null $user */
        $user = $users->find($id);
        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        // jointure participant -> ride -> vehicle -> brand -> driver
        $qb = $em->createQueryBuilder()
            ->select('rp', 'r', 'v', 'b', 'd')
            ->from(RideParticipant::class, 'rp')
            ->join('rp.ride', 'r')
            ->join('r.vehicle', 'v')
            ->join('v.brand', 'b')
            ->join('r.driver', 'd')
            ->where('rp.user = :u')
            ->orderBy('r.startAt', 'DESC')
            ->setParameter('u', $user);

        $rows = $qb->getQuery()->getResult();

        $data = array_map(function (RideParticipant $rp) {
            $r = $rp->getRide();
            return [
                'participantId' => $rp->getId(),
                'status'        => $rp->getStatus(),
                'seatsBooked'   => $rp->getSeatsBooked(),
                'creditsUsed'   => $rp->getCreditsUsed(),
                'ride'          => [
                    'id'         => $r->getId(),
                    'from'       => $r->getFromCity(),
                    'to'         => $r->getToCity(),
                    'startAt'    => $r->getStartAt()?->format('Y-m-d H:i'),
                    'endAt'      => $r->getEndAt()?->format('Y-m-d H:i'),
                    'price'      => (float)$r->getPrice(),
                    'seatsLeft'  => $r->getSeatsLeft(),
                    'seatsTotal' => $r->getSeatsTotal(),
                    'vehicle'    => [
                        'brand' => $r->getVehicle()->getBrand()->getName(),
                        'model' => $r->getVehicle()->getModel(),
                        'eco'   => $r->getVehicle()->isEco(),
                    ],
                    'driver'     => [
                        'id'     => $r->getDriver()->getId(),
                        'pseudo' => $r->getDriver()->getPseudo(),
                    ],
                ],
            ];
        }, $rows);

        return $this->json($data);
    }

    #[Route('/users/{id}/rides', name: 'my_rides', methods: ['GET'])]
    public function myRides(
        int $id,
        UserRepository $users,
        RideRepository $rides
    ): JsonResponse {
        $user = $users->find($id);
        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        $list = $rides->createQueryBuilder('r')
            ->addSelect('v','b')
            ->join('r.vehicle','v')
            ->join('v.brand','b')
            ->where('r.driver = :u')
            ->setParameter('u', $user)
            ->orderBy('r.startAt','DESC')
            ->getQuery()->getResult();

        $data = array_map(function($r) {
            return [
                'id'         => $r->getId(),
                'from'       => $r->getFromCity(),
                'to'         => $r->getToCity(),
                'startAt'    => $r->getStartAt()?->format('Y-m-d H:i'),
                'endAt'      => $r->getEndAt()?->format('Y-m-d H:i'),
                'price'      => (float)$r->getPrice(),
                'seatsLeft'  => $r->getSeatsLeft(),
                'seatsTotal' => $r->getSeatsTotal(),
                'status'     => $r->getStatus(),
                'vehicle'    => [
                    'brand' => $r->getVehicle()->getBrand()->getName(),
                    'model' => $r->getVehicle()->getModel(),
                    'eco'   => $r->getVehicle()->isEco(),
                ],
            ];
        }, $list);

        return $this->json($data);
    }
}
