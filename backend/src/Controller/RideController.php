<?php

namespace App\Controller;

use App\Repository\RideRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api', name: 'api_')]
class RideController extends AbstractController
{
    #[Route('/health', name: 'health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return $this->json([
            'status'  => 'ok',
            'version' => 'v1',
            'time'    => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * GET /api/rides?from=Paris&to=Lille&date=2025-11-10
     * – from / to : filtre exact sur les villes (case-insensitive)
     * – date : YYYY-MM-DD (on sélectionne les trajets qui démarrent ce jour-là)
     */
    #[Route('/rides', name: 'rides', methods: ['GET'])]
    public function list(Request $req, RideRepository $repo): JsonResponse
    {
        $from = $req->query->get('from');
        $to   = $req->query->get('to');
        $date = $req->query->get('date'); // YYYY-MM-DD

        $qb = $repo->createQueryBuilder('r')
            ->addSelect('v', 'b', 'd')
            ->join('r.vehicle', 'v')
            ->join('v.brand', 'b')
            ->join('r.driver', 'd')
            ->orderBy('r.startAt', 'ASC');

        if ($from) {
            $qb->andWhere('LOWER(r.fromCity) = LOWER(:from)')->setParameter('from', $from);
        }
        if ($to) {
            $qb->andWhere('LOWER(r.toCity) = LOWER(:to)')->setParameter('to', $to);
        }
        if ($date) {
            $start = new \DateTimeImmutable($date.' 00:00:00');
            $end   = new \DateTimeImmutable($date.' 23:59:59');
            $qb->andWhere('r.startAt BETWEEN :s AND :e')
               ->setParameter('s', $start)->setParameter('e', $end);
        }

        $rides = $qb->getQuery()->getResult();

        // petite normalisation JSON
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
                'driver'     => [
                    'id'     => $r->getDriver()->getId(),
                    'pseudo' => $r->getDriver()->getPseudo(),
                ],
            ];
        }, $rides);

        return $this->json($data);
    }
}
