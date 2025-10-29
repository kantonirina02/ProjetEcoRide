<?php

namespace App\Controller;

use App\Entity\Ride;
use App\Entity\RideParticipant;
use App\Repository\RideRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

    #[Route('/debug/echo', name: 'debug_echo', methods: ['POST'])]
    public function debugEcho(Request $req): JsonResponse
    {
        return $this->json([
            'method'  => $req->getMethod(),
            'headers' => $req->headers->all(),
            'content' => $req->getContent(),
            'params'  => $req->request->all(),
            'query'   => $req->query->all(),
            'ctype'   => $req->headers->get('content-type'),
        ]);
    }

    /**
     * GET /api/rides?from=Paris&to=Lille&date=2025-11-10&eco=1&priceMax=25&durationMax=150
     */
    #[Route('/rides', name: 'rides_list', methods: ['GET'])]
    public function list(Request $req, RideRepository $repo): JsonResponse
    {
        $from        = $req->query->get('from');
        $to          = $req->query->get('to');
        $date        = $req->query->get('date');
        $eco         = $req->query->get('eco');
        $priceMax    = $req->query->get('priceMax');
        $durationMax = $req->query->get('durationMax'); // en minutes

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
            try {
                $start = new \DateTimeImmutable($date.' 00:00:00');
                $end   = new \DateTimeImmutable($date.' 23:59:59');
                $qb->andWhere('r.startAt BETWEEN :s AND :e')
                   ->setParameter('s', $start)->setParameter('e', $end);
            } catch (\Exception $e) {
                return $this->json(['error' => 'Invalid date format, expected YYYY-MM-DD'], Response::HTTP_BAD_REQUEST);
            }
        }
        if ($eco !== null && $eco !== '') {
            $qb->andWhere('v.eco = :eco')->setParameter('eco', (bool)((int)$eco));
        }
        if ($priceMax !== null && $priceMax !== '') {
            $qb->andWhere('r.price <= :pmax')->setParameter('pmax', (float)$priceMax);
        }

        /** @var Ride[] $rides */
        $rides = $qb->getQuery()->getResult();

        // durationMax traité en PHP pour éviter la fonction SQL non supportée par DQL
        if ($durationMax !== null && $durationMax !== '') {
            $max = (int) $durationMax;
            $rides = array_values(array_filter($rides, static function (Ride $r) use ($max) {
                $start = $r->getStartAt();
                $end   = $r->getEndAt();
                if (!$start || !$end) {
                    return false;
                }
                $minutes = (int) round(($end->getTimestamp() - $start->getTimestamp()) / 60);
                return $minutes <= $max;
            }));
        }

        $data = array_map(static function (Ride $r): array {
            return [
                'id'         => $r->getId(),
                'from'       => $r->getFromCity(),
                'to'         => $r->getToCity(),
                'startAt'    => $r->getStartAt()?->format('Y-m-d H:i'),
                'endAt'      => $r->getEndAt()?->format('Y-m-d H:i'),
                'price'      => (float) $r->getPrice(),
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

    #[Route('/rides/{id}/book', name: 'ride_book', methods: ['POST'])]
    public function book(
        int $id,
        Request $req,
        RideRepository $rides,
        EntityManagerInterface $em
    ): JsonResponse {
        $ride = $rides->find($id);
        if (!$ride) {
            return $this->json(['error' => 'Ride not found'], Response::HTTP_NOT_FOUND);
        }

        $userId = 0;
        $seats  = 0;

        $raw = $req->getContent() ?? '';
        if ($raw !== '') {
            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $userId = (int)($decoded['userId'] ?? 0);
                    $seats  = (int)($decoded['seats'] ?? 0);
                }
            } catch (\JsonException) {}
        }

        if ($userId <= 0 || $seats <= 0) {
            $userId = (int) ($req->request->get('userId') ?? $req->query->get('userId', 0));
            $seats  = (int) ($req->request->get('seats')  ?? $req->query->get('seats', 0));
        }

        if ($userId <= 0 || $seats <= 0) {
            return $this->json([
                'error' => 'Invalid payload (provide JSON {"userId":<int>,"seats":<int>} or form/query params)'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $em->beginTransaction();

            $ride = $em->getRepository(Ride::class)->find($id);
            if (!$ride) {
                $em->rollback();
                return $this->json(['error' => 'Ride not found'], Response::HTTP_NOT_FOUND);
            }

            if ($ride->getSeatsLeft() < $seats) {
                $em->rollback();
                return $this->json(['error' => 'Not enough seats'], Response::HTTP_CONFLICT);
            }

            $userRef = $em->getReference(\App\Entity\User::class, $userId);

            $existing = $em->getRepository(RideParticipant::class)->findOneBy([
                'ride' => $ride,
                'user' => $userRef,
            ]);
            if ($existing) {
                $em->rollback();
                return $this->json(['error' => 'Already booked'], Response::HTTP_CONFLICT);
            }

            $participant = (new RideParticipant())
                ->setRide($ride)
                ->setUser($userRef)
                ->setSeatsBooked($seats)
                ->setCreditsUsed(0)
                ->setStatus('confirmed');

            $ride->setSeatsLeft($ride->getSeatsLeft() - $seats);

            $em->persist($participant);
            $em->persist($ride);
            $em->flush();
            $em->commit();

            return $this->json([
                'ok'        => true,
                'rideId'    => $ride->getId(),
                'userId'    => $userId,
                'seats'     => $seats,
                'seatsLeft' => $ride->getSeatsLeft(),
                'status'    => 'confirmed',
            ], Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            if ($em->getConnection()->isTransactionActive()) {
                $em->rollback();
            }
            return $this->json([
                'error'  => 'Booking failed',
                'detail' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
