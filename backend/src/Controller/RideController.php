<?php

namespace App\Controller;

use App\Entity\Brand;
use App\Entity\CreditLedger;
use App\Entity\Ride;
use App\Entity\RideParticipant;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Repository\ReviewRepository;
use App\Repository\RideRepository;
use App\Service\SearchLogger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api', name: 'api_')]
class RideController extends AbstractController
{
    private const PLATFORM_FEE = 2;

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
     * Liste des trajets avec filtres (from, to, date, eco, priceMax, durationMax SQL, ratingMin côté PHP)
     */
    #[Route('/rides', name: 'rides_list', methods: ['GET'])]
    public function list(
        Request $req,
        RideRepository $repo,
        ReviewRepository $reviewRepo,
        SearchLogger $searchLogger
    ): JsonResponse {
        $from        = $req->query->get('from');
        $to          = $req->query->get('to');
        $date        = $req->query->get('date');
        $eco         = $req->query->get('eco');
        $priceMax    = $req->query->get('priceMax');
        $durationMax = $req->query->get('durationMax'); // minutes
        $ratingMin   = $req->query->get('ratingMin');

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
                $start = new \DateTimeImmutable($date . ' 00:00:00');
                $end   = new \DateTimeImmutable($date . ' 23:59:59');
                $qb->andWhere('r.startAt BETWEEN :s AND :e')
                    ->setParameter('s', $start)
                    ->setParameter('e', $end);
            } catch (\Exception) {
                return $this->json(['error' => 'Invalid date format, expected YYYY-MM-DD'], Response::HTTP_BAD_REQUEST);
            }
        }
        if ($eco !== null && $eco !== '') {
            $qb->andWhere('v.eco = :eco')->setParameter('eco', (bool)((int)$eco));
        }
        if ($priceMax !== null && $priceMax !== '') {
            // DECIMAL => passer en string
            $qb->andWhere('r.price <= :pmax')->setParameter('pmax', (string)$priceMax);
        }
        if ($durationMax !== null && $durationMax !== '') {
            // MySQL/MariaDB
            $qb->andWhere('r.endAt IS NOT NULL');
            $qb->andWhere("FUNCTION('TIMESTAMPDIFF', 'MINUTE', r.startAt, r.endAt) <= :durationMax")
               ->setParameter('durationMax', (int)$durationMax);
        }

        /** @var Ride[] $rides */
        $rides = $qb->getQuery()->getResult();

        // Prépare la map des notes chauffeurs
        $driverIds = [];
        foreach ($rides as $ride) {
            $driver = $ride->getDriver();
            if ($driver && $driver->getId()) {
                $driverIds[] = $driver->getId();
            }
        }

        $ratingMap = [];
        if ($driverIds) {
            $ratingRows = $reviewRepo->createQueryBuilder('rev')
                ->select('IDENTITY(rev.target) AS targetId', 'AVG(rev.rating) AS avgRating', 'COUNT(rev.id) AS reviewCount')
                ->where('rev.target IN (:ids)')
                ->setParameter('ids', array_unique($driverIds))
                ->groupBy('rev.target')
                ->getQuery()
                ->getArrayResult();

            foreach ($ratingRows as $row) {
                $ratingMap[(int)$row['targetId']] = [
                    'avg'   => round((float)$row['avgRating'], 1),
                    'count' => (int)$row['reviewCount'],
                ];
            }
        }

        // Filtre rating côté PHP (avec fallback pour conducteurs sans note)
        if ($ratingMin !== null && $ratingMin !== '') {
            $minRating = (float)$ratingMin;
            $rides = array_values(array_filter($rides, static function (Ride $ride) use ($ratingMap, $minRating) {
                $driver = $ride->getDriver();
                if (!$driver) {
                    return $minRating <= 0;
                }
                $driverId = $driver->getId();
                if ($driverId === null) {
                    return $minRating <= 0;
                }
                $info = $ratingMap[$driverId] ?? null;
                if (!$info) {
                    return $minRating <= 0;
                }
                return $info['avg'] >= $minRating;
            }));
        }

        // Construction de la payload front
        $data = array_map(function (Ride $r) use ($ratingMap): array {
            $vehicle  = $r->getVehicle();
            $brand    = $vehicle?->getBrand();
            $driver   = $r->getDriver();
            $driverId = $driver?->getId();
            $rating   = $driverId && isset($ratingMap[$driverId]) ? $ratingMap[$driverId] : null;

            return [
                'id'         => $r->getId(),
                'from'       => $r->getFromCity(),
                'to'         => $r->getToCity(),
                'startAt'    => $r->getStartAt()?->format('Y-m-d H:i'),
                'endAt'      => $r->getEndAt()?->format('Y-m-d H:i'),
                'price'      => $r->getPrice() !== null ? (float)$r->getPrice() : null,
                'seatsLeft'  => $r->getSeatsLeft(),
                'seatsTotal' => $r->getSeatsTotal(),
                'status'     => $r->getStatus(),
                'soldOut'    => ($r->getSeatsLeft() ?? 0) <= 0,
                'vehicle'    => $vehicle ? [
                    'brand' => $brand?->getName(),
                    'model' => $vehicle->getModel(),
                    'eco'   => (bool)($vehicle->isEco() ?? false),
                ] : null,
                'driver'     => $driver ? [
                    'id'      => $driver->getId(),
                    'pseudo'  => $driver->getPseudo(),
                    'rating'  => $rating['avg']   ?? null,
                    'reviews' => $rating['count'] ?? 0,
                    'photo'   => $this->guessDriverPhoto($driver),
                ] : null,
            ];
        }, $rides);

        // Suggestion de date proche si aucun résultat
        $suggestion = null;
        if (!$data && $from && $to) {
            $suggestQb = $repo->createQueryBuilder('r')
                ->where('LOWER(r.fromCity) = LOWER(:from)')
                ->andWhere('LOWER(r.toCity) = LOWER(:to)')
                ->setParameters(['from' => $from, 'to' => $to])
                ->orderBy('r.startAt', 'ASC')
                ->setMaxResults(1);

            if ($date) {
                try {
                    $searchDate = new \DateTimeImmutable($date);
                    $suggestQb->andWhere('r.startAt >= :searchDate')
                              ->setParameter('searchDate', $searchDate);
                } catch (\Exception) {
                    // ignore date invalide
                }
            } else {
                $suggestQb->andWhere('r.startAt >= :now')->setParameter('now', new \DateTimeImmutable('now'));
            }

            $closest = $suggestQb->getQuery()->getOneOrNullResult();
            if ($closest instanceof Ride) {
                $suggestion = [
                    'rideId'  => $closest->getId(),
                    'date'    => $closest->getStartAt()?->format('Y-m-d'),
                    'startAt' => $closest->getStartAt()?->format('Y-m-d H:i'),
                    'from'    => $closest->getFromCity(),
                    'to'      => $closest->getToCity(),
                ];
            }
        }

        // --- LOG MongoDB (non bloquant) ---
        $sessionUserId = (int)($req->getSession()->get('user_id') ?? 0) ?: null;
        $searchLogger->log($from, $to, $date, is_countable($data) ? count($data) : 0, $sessionUserId);

        return $this->json([
            'rides'      => $data,
            'suggestion' => $suggestion,
        ]);
    }

    /**
     * Réservation par l'utilisateur connecté (session).
     * POST /api/rides/{id}/book
     * Body JSON (optionnel) : { "seats": 1, "confirm": true }
     */
    #[Route('/rides/{id<\d+>}/book', name: 'ride_book', methods: ['POST'])]
    public function book(
        int $id,
        Request $req,
        RideRepository $rides,
        EntityManagerInterface $em
    ): JsonResponse {
        // Auth via session (dev/local)
        $session = $req->getSession();
        $userId = (int)($session->get('user_id') ?? 0);
        if ($userId <= 0) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        /** @var Ride|null $ride */
        $ride = $rides->find($id);
        if (!$ride) {
            return $this->json(['error' => 'Ride not found'], Response::HTTP_NOT_FOUND);
        }

        // interdire au conducteur de réserver son propre trajet
        if ($ride->getDriver() && $ride->getDriver()->getId() === $userId) {
            return $this->json(['error' => 'Driver cannot book own ride'], Response::HTTP_CONFLICT);
        }

        // interdire si trajet déjà commencé/passé
        $now = new \DateTimeImmutable('now');
        if ($ride->getStartAt() && $ride->getStartAt() < $now) {
            return $this->json(['error' => 'Ride already started or past'], Response::HTTP_CONFLICT);
        }

        $raw = $req->getContent() ?? '';
        $payload = null;
        if ($raw !== '') {
            try {
                $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
            }
        }

        // Seats (default 1)
        $seats = 1;
        if (is_array($payload) && isset($payload['seats'])) {
            $seats = max(1, (int)$payload['seats']);
        }
        if ($seats <= 0) {
            $formSeats = $req->request->get('seats') ?? $req->query->get('seats');
            if ($formSeats !== null) {
                $seats = max(1, (int)$formSeats);
            }
            if ($seats <= 0) {
                $seats = 1;
            }
        }

        // Confirmation explicite (2-step)
        $confirmFlag = null;
        if (is_array($payload) && array_key_exists('confirm', $payload)) {
            $confirmFlag = (bool)$payload['confirm'];
        }
        $confirmParam = $req->query->get('confirm', $req->request->get('confirm'));
        if ($confirmFlag === null && $confirmParam !== null) {
            $normalized = filter_var($confirmParam, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($normalized !== null) {
                $confirmFlag = $normalized;
            }
        }
        $confirm = $confirmFlag === true;

        /** @var User|null $passenger */
        $passenger = $em->getRepository(User::class)->find($userId);
        if (!$passenger) {
            return $this->json(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
        }

        $costCredits = $this->computeCostCredits($ride, $seats);
        $availableCredits = $passenger->getCreditsBalance();
        if ($availableCredits < $costCredits) {
            return $this->json([
                'error'     => 'Insufficient credits',
                'required'  => $costCredits,
                'available' => $availableCredits,
            ], Response::HTTP_CONFLICT);
        }

        if ($ride->getSeatsLeft() < $seats) {
            return $this->json(['error' => 'Not enough seats'], Response::HTTP_CONFLICT);
        }

        if (!$confirm) {
            return $this->json([
                'requiresConfirmation' => true,
                'rideId'          => $ride->getId(),
                'seats'           => $seats,
                'costCredits'     => $costCredits,
                'availableCredits'=> $availableCredits,
            ], Response::HTTP_ACCEPTED);
        }

        try {
            $em->beginTransaction();

            /** @var Ride|null $ride */
            $ride = $em->getRepository(Ride::class)->find($id);
            if (!$ride) {
                $em->rollback();
                return $this->json(['error' => 'Ride not found'], Response::HTTP_NOT_FOUND);
            }

            if ($ride->getSeatsLeft() < $seats) {
                $em->rollback();
                return $this->json(['error' => 'Not enough seats'], Response::HTTP_CONFLICT);
            }

            /** @var User|null $passenger */
            $passenger = $em->getRepository(User::class)->find($userId);
            if (!$passenger) {
                $em->rollback();
                return $this->json(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
            }

            $costCredits = $this->computeCostCredits($ride, $seats);
            if ($passenger->getCreditsBalance() < $costCredits) {
                $em->rollback();
                return $this->json([
                    'error'     => 'Insufficient credits',
                    'required'  => $costCredits,
                    'available' => $passenger->getCreditsBalance(),
                ], Response::HTTP_CONFLICT);
            }

            $participantRepo = $em->getRepository(RideParticipant::class);
            $existing = $participantRepo->findOneBy(['ride' => $ride, 'user' => $passenger]);
            if ($existing && $existing->getStatus() !== 'cancelled') {
                $em->rollback();
                return $this->json(['error' => 'Already booked'], Response::HTTP_CONFLICT);
            }

            $participant = $existing ?? (new RideParticipant())->setRide($ride)->setUser($passenger);

            $now = new \DateTimeImmutable();
            $participant
                ->setSeatsBooked($seats)
                ->setCreditsUsed($costCredits)
                ->setStatus('confirmed')
                ->setRequestedAt($now)
                ->setConfirmedAt($now)
                ->setCancelledAt(null);

            $ride->setSeatsLeft(max(0, ($ride->getSeatsLeft() ?? 0) - $seats));

            $passenger->setCreditsBalance($passenger->getCreditsBalance() - $costCredits);
            $this->recordLedger($em, $passenger, $ride, -$costCredits, 'ride_booking');

            $driverShare = 0;
            $driver = $ride->getDriver();
            if ($driver && $driver->getId() !== $passenger->getId()) {
                $driverShare = max(0, $costCredits - self::PLATFORM_FEE);
                if ($driverShare > 0) {
                    $driver->setCreditsBalance($driver->getCreditsBalance() + $driverShare);
                    $this->recordLedger($em, $driver, $ride, $driverShare, 'ride_income');
                    $em->persist($driver);
                }
            }

            $em->persist($participant);
            $em->persist($ride);
            $em->persist($passenger);
            $em->flush();
            $em->commit();

            return $this->json([
                'ok'          => true,
                'rideId'      => $ride->getId(),
                'seats'       => $seats,
                'seatsLeft'   => $ride->getSeatsLeft(),
                'status'      => $participant->getStatus(),
                'costCredits' => $costCredits,
                'driverShare' => $driverShare,
                'balance'     => $passenger->getCreditsBalance(),
            ], Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            if ($em->getConnection()->isTransactionActive()) {
                $em->rollback();
            }
            return $this->json(['error' => 'Booking failed', 'detail' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Annuler sa réservation (utilisateur connecté).
     * DELETE /api/rides/{id}/book
     */
    #[Route('/rides/{id<\d+>}/book', name: 'ride_unbook', methods: ['DELETE'])]
    public function unbook(
        int $id,
        Request $req,
        EntityManagerInterface $em
    ): JsonResponse {
        $session = $req->getSession();
        $userId = (int)($session->get('user_id') ?? 0);
        if ($userId <= 0) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        /** @var Ride|null $ride */
        $ride = $em->getRepository(Ride::class)->find($id);
        if (!$ride) {
            return $this->json(['error' => 'Ride not found'], Response::HTTP_NOT_FOUND);
        }

        $participant = $em->getRepository(RideParticipant::class)->findOneBy([
            'ride' => $ride,
            'user' => $em->getReference(User::class, $userId),
        ]);
        if (!$participant) {
            return $this->json(['error' => 'Not booked'], Response::HTTP_NOT_FOUND);
        }

        if ($participant->getStatus() === 'cancelled') {
            return $this->json(['error' => 'Booking already cancelled'], Response::HTTP_CONFLICT);
        }

        if ($ride->getStartAt() && $ride->getStartAt() <= new \DateTimeImmutable('now')) {
            return $this->json(['error' => 'Ride already started'], Response::HTTP_CONFLICT);
        }

        try {
            $em->beginTransaction();

            /** @var User|null $passenger */
            $passenger = $em->getRepository(User::class)->find($userId);
            if (!$passenger) {
                $em->rollback();
                return $this->json(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
            }

            $seats = $participant->getSeatsBooked();
            $currentLeft = $ride->getSeatsLeft() ?? 0;
            $totalSeats = $ride->getSeatsTotal() ?? ($currentLeft + $seats);
            $ride->setSeatsLeft(min($totalSeats, $currentLeft + $seats));

            $creditsUsed = (int)($participant->getCreditsUsed() ?? 0);
            if ($creditsUsed > 0) {
                $passenger->setCreditsBalance($passenger->getCreditsBalance() + $creditsUsed);
                $this->recordLedger($em, $passenger, $ride, $creditsUsed, 'ride_refund');
            }

            $driverShare = 0;
            $driver = $ride->getDriver();
            if ($driver && $driver->getId() !== $passenger->getId() && $creditsUsed > 0) {
                $driverShare = max(0, $creditsUsed - self::PLATFORM_FEE);
                if ($driverShare > 0) {
                    $driver->setCreditsBalance($driver->getCreditsBalance() - $driverShare);
                    $this->recordLedger($em, $driver, $ride, -$driverShare, 'ride_income_reversal');
                    $em->persist($driver);
                }
            }

            $participant
                ->setStatus('cancelled')
                ->setCancelledAt(new \DateTimeImmutable());

            $em->persist($passenger);
            $em->persist($ride);
            $em->persist($participant);
            $em->flush();
            $em->commit();

            return $this->json([
                'ok'                 => true,
                'rideId'             => $ride->getId(),
                'seatsLeft'          => $ride->getSeatsLeft(),
                'refundedCredits'    => $creditsUsed,
                'driverShareDebited' => $driverShare,
            ]);
        } catch (\Throwable $e) {
            if ($em->getConnection()->isTransactionActive()) {
                $em->rollback();
            }
            return $this->json(['error' => 'Cancellation failed', 'detail' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Mes réservations (session)
     */
    #[Route('/me/bookings', name: 'my_bookings', methods: ['GET'])]
    public function myBookings(Request $req, EntityManagerInterface $em): JsonResponse
    {
        $uid = (int)($req->getSession()->get('user_id') ?? 0);
        if ($uid <= 0) {
            return $this->json(['auth' => false, 'bookings' => []], Response::HTTP_OK);
        }
        $userRef = $em->getReference(User::class, $uid);
        $parts = $em->getRepository(RideParticipant::class)->findBy(['user' => $userRef], ['id' => 'DESC']);

        $out = [];
        foreach ($parts as $p) {
            $r = $p->getRide();
            $out[] = [
                'rideId'  => $r->getId(),
                'from'    => $r->getFromCity(),
                'to'      => $r->getToCity(),
                'startAt' => $r->getStartAt()?->format('Y-m-d H:i'),
                'seats'   => $p->getSeatsBooked(),
                'status'  => $p->getStatus(),
            ];
        }

        return $this->json(['auth' => true, 'bookings' => $out]);
    }

    /**
     * Mes trajets en tant que conducteur (session)
     */
    #[Route('/me/rides', name: 'my_rides', methods: ['GET'])]
    public function myRides(Request $req, RideRepository $repo): JsonResponse
    {
        $uid = (int)($req->getSession()->get('user_id') ?? 0);
        if ($uid <= 0) {
            return $this->json(['auth' => false, 'rides' => []], Response::HTTP_OK);
        }

        $qb = $repo->createQueryBuilder('r')
            ->addSelect('v', 'b', 'd')
            ->join('r.vehicle', 'v')
            ->join('v.brand', 'b')
            ->join('r.driver', 'd')
            ->where('d.id = :uid')
            ->setParameter('uid', $uid)
            ->orderBy('r.startAt', 'DESC');

        /** @var Ride[] $rides */
        $rides = $qb->getQuery()->getResult();

        $out = array_map(static function (Ride $r): array {
            $vehicle = $r->getVehicle();
            $brand   = $vehicle?->getBrand();
            return [
                'id'         => $r->getId(),
                'from'       => $r->getFromCity(),
                'to'         => $r->getToCity(),
                'startAt'    => $r->getStartAt()?->format('Y-m-d H:i'),
                'endAt'      => $r->getEndAt()?->format('Y-m-d H:i'),
                'price'      => $r->getPrice() !== null ? (float)$r->getPrice() : null,
                'seatsLeft'  => $r->getSeatsLeft(),
                'seatsTotal' => $r->getSeatsTotal(),
                'status'     => $r->getStatus(),
                'vehicle'    => $vehicle ? [
                    'brand' => $brand?->getName(),
                    'model' => $vehicle->getModel(),
                    'eco'   => (bool)($vehicle->isEco() ?? false),
                ] : null,
            ];
        }, $rides);

        return $this->json($out);
    }

    /**
     * Mes véhicules (session)
     */
    #[Route('/me/vehicles', name: 'my_vehicles', methods: ['GET'])]
    public function myVehicles(Request $req, EntityManagerInterface $em): JsonResponse
    {
        $uid = (int)($req->getSession()->get('user_id') ?? 0);
        if ($uid <= 0) {
            return $this->json(['auth' => false, 'vehicles' => []], Response::HTTP_OK);
        }

        /** @var User|null $driver */
        $driver = $em->getRepository(User::class)->find($uid);
        if (!$driver) {
            return $this->json(['auth' => false, 'vehicles' => []], Response::HTTP_OK);
        }

        $vehicles = $em->getRepository(Vehicle::class)->findBy(['owner' => $driver], ['model' => 'ASC']);

        $data = array_map(static function (Vehicle $v): array {
            $brand = $v->getBrand();
            return [
                'id'         => $v->getId(),
                'label'      => trim(($brand?->getName() ?? '') . ' ' . ($v->getModel() ?? '')),
                'seatsTotal' => $v->getSeatsTotal(),
                'eco'        => (bool)($v->isEco() ?? false),
                'energy'     => $v->getEnergy(),
                'color'      => $v->getColor(),
            ];
        }, $vehicles);

        return $this->json(['auth' => true, 'vehicles' => $data]);
    }

    /**
     * Trajets d’un conducteur (public / pour page account)
     */
    #[Route('/users/{id<\d+>}/rides', name: 'user_rides', methods: ['GET'])]
    public function userRides(int $id, EntityManagerInterface $em): JsonResponse
    {
        $userRef = $em->getReference(User::class, $id);
        $rides = $em->getRepository(Ride::class)->findBy(['driver' => $userRef], ['startAt' => 'DESC']);

        $out = [];
        foreach ($rides as $r) {
            $v = $r->getVehicle();
            $out[] = [
                'id'         => $r->getId(),
                'from'       => $r->getFromCity(),
                'to'         => $r->getToCity(),
                'startAt'    => $r->getStartAt()?->format('Y-m-d H:i'),
                'endAt'      => $r->getEndAt()?->format('Y-m-d H:i'),
                'price'      => $r->getPrice() !== null ? (float)$r->getPrice() : null,
                'seatsLeft'  => $r->getSeatsLeft(),
                'seatsTotal' => $r->getSeatsTotal(),
                'status'     => $r->getStatus(),
                'vehicle'    => $v ? [
                    'brand' => $v->getBrand()?->getName(),
                    'model' => $v->getModel(),
                    'eco'   => (bool)($v->isEco() ?? false),
                ] : null,
            ];
        }

        return $this->json($out);
    }

    /**
     * Réservations d’un utilisateur
     */
    #[Route('/users/{id<\d+>}/bookings', name: 'user_bookings', methods: ['GET'])]
    public function userBookings(int $id, EntityManagerInterface $em): JsonResponse
    {
        $userRef = $em->getReference(User::class, $id);
        $parts = $em->getRepository(RideParticipant::class)->findBy(['user' => $userRef], ['id' => 'DESC']);

        $out = [];
        foreach ($parts as $p) {
            $r = $p->getRide();
            $driver = $r->getDriver();
            $out[] = [
                'id'          => $p->getId(),
                'seatsBooked' => $p->getSeatsBooked(),
                'status'      => $p->getStatus(),
                'ride'        => [
                    'id'      => $r->getId(),
                    'from'    => $r->getFromCity(),
                    'to'      => $r->getToCity(),
                    'startAt' => $r->getStartAt()?->format('Y-m-d H:i'),
                    'price'   => $r->getPrice() !== null ? (float)$r->getPrice() : null,
                    'driver'  => $driver ? [
                        'id'     => $driver->getId(),
                        'pseudo' => $driver->getPseudo(),
                    ] : null,
                ],
            ];
        }

        return $this->json($out);
    }

    /**
     * POST /api/rides (création d'un trajet)
     * Utilise un véhicule existant (vehicleId) ou crée/synchronise depuis le payload vehicle{}.
     * Débite 2 crédits (commission de publication).
     */
    #[Route('/rides', name: 'ride_create', methods: ['POST'])]
    public function createRide(Request $req, ManagerRegistry $doctrine): JsonResponse
    {
        $session = $req->getSession();
        $userId = (int)($session->get('user_id') ?? 0);
        if ($userId <= 0) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        $em = $doctrine->getManager();

        $raw = $req->getContent() ?? '';
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->json(['error' => 'Corps JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $vehicleId = isset($data['vehicleId']) ? (int)$data['vehicleId'] : null;
        if ($vehicleId !== null && $vehicleId <= 0) {
            $vehicleId = null;
        }

        $errors = [];
        $isInt  = static fn($v) => filter_var($v, FILTER_VALIDATE_INT) !== false;
        $isNum  = static fn($v) => is_numeric($v);
        $hasVehiclePayload = isset($data['vehicle']) && is_array($data['vehicle']);
        if (!$vehicleId && !$hasVehiclePayload) $errors[] = 'vehicle manquant';
        if (!isset($data['fromCity']) || trim((string)$data['fromCity']) === '') $errors[] = 'fromCity requis';
        if (!isset($data['toCity']) || trim((string)$data['toCity']) === '') $errors[] = 'toCity requis';
        if (!isset($data['startAt']) || trim((string)$data['startAt']) === '') $errors[] = 'startAt requis';
        if (!isset($data['endAt']) || trim((string)$data['endAt']) === '') $errors[] = 'endAt requis';
        if (!isset($data['price']) || !$isNum($data['price'])) $errors[] = 'price invalide';
        if ($errors) {
            return $this->json(['error' => 'Validation failed', 'details' => $errors], Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['driverId']) && $isInt($data['driverId']) && (int)$data['driverId'] !== $userId) {
            return $this->json(['error' => 'driverId ne correspond pas à l’utilisateur connecté'], Response::HTTP_FORBIDDEN);
        }

        /** @var User|null $driver */
        $driver = $em->getRepository(User::class)->find($userId);
        if (!$driver) {
            return $this->json(['error' => 'Driver not found'], Response::HTTP_NOT_FOUND);
        }

        $priceValue = (float)$data['price'];
        if ($priceValue <= self::PLATFORM_FEE) {
            return $this->json(['error' => 'le prix doit être supérieur à la commission de 2 crédits'], Response::HTTP_BAD_REQUEST);
        }
        if ($driver->getCreditsBalance() < self::PLATFORM_FEE) {
            return $this->json(['error' => 'crédits insuffisants pour régler la commission'], Response::HTTP_CONFLICT);
        }

        try {
            $em->beginTransaction();

            $vehicle = null;

            if ($vehicleId) {
                $vehicle = $em->getRepository(Vehicle::class)->find($vehicleId);
                if (!$vehicle || $vehicle->getOwner()?->getId() !== $driver->getId()) {
                    $em->rollback();
                    return $this->json(['error' => 'vehicle invalide'], Response::HTTP_BAD_REQUEST);
                }
            } else {
                $vehInput  = $data['vehicle'] ?? [];
                $brandName = trim((string)($vehInput['brand'] ?? ''));
                if ($brandName === '') {
                    $em->rollback();
                    return $this->json(['error' => 'vehicle.brand est requis'], Response::HTTP_BAD_REQUEST);
                }

                $brandRepo = $em->getRepository(Brand::class);
                $brand = $brandRepo->findOneBy(['name' => $brandName]) ?? (new Brand())->setName($brandName);
                $em->persist($brand);

                $model = trim((string)($vehInput['model'] ?? ''));
                if ($model === '') {
                    $em->rollback();
                    return $this->json(['error' => 'vehicle.model est requis'], Response::HTTP_BAD_REQUEST);
                }

                $vehicle = $em->getRepository(Vehicle::class)->findOneBy([
                    'owner' => $driver,
                    'brand' => $brand,
                    'model' => $model,
                ]);

                $seatsTotal = isset($vehInput['seatsTotal']) ? max(1, (int)$vehInput['seatsTotal']) : 4;
                $eco   = (bool)($vehInput['eco'] ?? false);
                $color = $vehInput['color'] ?? null;
                $energy = (string)($vehInput['energy'] ?? 'electric');

                if (!$vehicle) {
                    $vehicle = (new Vehicle())
                        ->setOwner($driver)
                        ->setBrand($brand)
                        ->setModel($model)
                        ->setEco($eco)
                        ->setSeatsTotal($seatsTotal)
                        ->setColor($color)
                        ->setEnergy($energy)
                        ->setFirstRegistrationAt(new \DateTimeImmutable('2019-01-01'));
                    $em->persist($vehicle);
                } else {
                    $vehicle
                        ->setEco($eco)
                        ->setSeatsTotal($seatsTotal)
                        ->setColor($color)
                        ->setEnergy($energy);
                }
            }

            if (!$vehicle) {
                $em->rollback();
                return $this->json(['error' => 'vehicle introuvable'], Response::HTTP_BAD_REQUEST);
            }

            try {
                $start = new \DateTimeImmutable((string)$data['startAt']);
                $end   = new \DateTimeImmutable((string)$data['endAt']);
            } catch (\Exception) {
                $em->rollback();
                return $this->json(['error' => 'Format datetime invalide (attendu: Y-m-d H:i)'], Response::HTTP_BAD_REQUEST);
            }

            $now = new \DateTimeImmutable('now');
            if ($start < $now) {
                $em->rollback();
                return $this->json(['error' => 'startAt must be in the future'], Response::HTTP_BAD_REQUEST);
            }
            if ($end <= $start) {
                $em->rollback();
                return $this->json(['error' => 'endAt must be after startAt'], Response::HTTP_BAD_REQUEST);
            }
            if ($priceValue <= 0) {
                $em->rollback();
                return $this->json(['error' => 'price must be greater than 0'], Response::HTTP_BAD_REQUEST);
            }

            $seatsTotal = max(1, (int)($vehicle->getSeatsTotal() ?? 1));

            $ride = (new Ride())
                ->setDriver($driver)
                ->setVehicle($vehicle)
                ->setFromCity((string)$data['fromCity'])
                ->setToCity((string)$data['toCity'])
                ->setStartAt($start)
                ->setEndAt($end)
                ->setPrice((string)$priceValue)
                ->setSeatsTotal($seatsTotal)
                ->setSeatsLeft($seatsTotal)
                ->setStatus('open')
                ->setAllowSmoker((bool)($data['allowSmoker'] ?? false))
                ->setAllowAnimals((bool)($data['allowAnimals'] ?? false))
                ->setMusicStyle($data['musicStyle'] ?? null)
                ->setCreatedAt(new \DateTimeImmutable());

            // Commission plateforme (2 crédits)
            $driver->setCreditsBalance($driver->getCreditsBalance() - self::PLATFORM_FEE);
            $this->recordLedger($em, $driver, $ride, -self::PLATFORM_FEE, 'ride_publish_fee');

            $em->persist($ride);
            $em->persist($driver);
            $em->flush();
            $em->commit();

            return $this->json([
                'id'       => $ride->getId(),
                'driverId' => $driver->getId(),
                'vehicle'  => [
                    'brand'  => $vehicle->getBrand()?->getName(),
                    'model'  => $vehicle->getModel(),
                    'eco'    => $vehicle->isEco(),
                    'energy' => $vehicle->getEnergy(),
                    'seats'  => $vehicle->getSeatsTotal(),
                ],
                'from'     => $ride->getFromCity(),
                'to'       => $ride->getToCity(),
                'startAt'  => $ride->getStartAt()->format('Y-m-d H:i'),
                'endAt'    => $ride->getEndAt()->format('Y-m-d H:i'),
                'price'    => (float)$ride->getPrice(),
                'status'   => $ride->getStatus(),
                'balance'  => $driver->getCreditsBalance(),
            ], Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            if ($em->getConnection()->isTransactionActive()) {
                $em->rollback();
            }
            return $this->json(['error' => 'Creation failed', 'detail' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Détail d’un trajet + participants
     */
    #[Route('/rides/{id<\d+>}', name: 'ride_show', methods: ['GET'])]
    public function showRide(int $id, Request $req, EntityManagerInterface $em): JsonResponse
    {
        /** @var Ride|null $r */
        $r = $em->getRepository(Ride::class)->find($id);
        if (!$r) {
            return $this->json(['error' => 'Ride not found'], Response::HTTP_NOT_FOUND);
        }

        $sessionUserId = (int)($req->getSession()->get('user_id') ?? 0);
        $v = $r->getVehicle();
        $brand = $v?->getBrand();
        $driver = $r->getDriver();

        $isDriverViewing = $sessionUserId > 0 && $driver && $driver->getId() === $sessionUserId;

        // participants
        $parts = $em->getRepository(RideParticipant::class)->findBy(['ride' => $r], ['id' => 'ASC']);
        $participants = [];
        foreach ($parts as $p) {
            $u = $p->getUser();
            $isSelf = $sessionUserId > 0 && $u && $u->getId() === $sessionUserId;
            $participants[] = [
                'user' => $u ? [
                    'id'     => $u->getId(),
                    'pseudo' => $u->getPseudo(),
                    // on ne montre l’email que pour le conducteur ou soi-même
                    'email'  => ($isDriverViewing || $isSelf) ? $u->getEmail() : null,
                ] : null,
                'seats'  => $p->getSeatsBooked(),
                'status' => $p->getStatus(),
            ];
        }

        return $this->json([
            'id'         => $r->getId(),
            'from'       => $r->getFromCity(),
            'to'         => $r->getToCity(),
            'startAt'    => $r->getStartAt()?->format('Y-m-d H:i'),
            'endAt'      => $r->getEndAt()?->format('Y-m-d H:i'),
            'price'      => $r->getPrice() !== null ? (float)$r->getPrice() : null,
            'seatsLeft'  => (int)$r->getSeatsLeft(),
            'seatsTotal' => (int)$r->getSeatsTotal(),
            'status'     => $r->getStatus(),
            'vehicle'    => $v ? [
                'brand'  => $brand?->getName(),
                'model'  => $v->getModel(),
                'eco'    => (bool)($v->isEco() ?? false),
                'energy' => $v->getEnergy(),
                'color'  => $v->getColor(),
            ] : null,
            'driver' => $driver ? [
                'id'     => $driver->getId(),
                'pseudo' => $driver->getPseudo(),
                // email visible uniquement pour le conducteur lui-même
                'email'  => ($isDriverViewing && $sessionUserId === $driver->getId()) ? $driver->getEmail() : null,
            ] : null,
            'participants' => $participants,
        ]);
    }

    private function computeCostCredits(Ride $ride, int $seats): int
    {
        $price = (float)($ride->getPrice() ?? 0);
        $perSeat = (int)ceil($price);
        if ($perSeat <= 0) {
            $perSeat = 1;
        }
        return max(1, $seats) * $perSeat;
    }

    private function recordLedger(EntityManagerInterface $em, User $user, Ride $ride, int $delta, string $source): void
    {
        $ledger = (new CreditLedger())
            ->setUser($user)
            ->setRide($ride)
            ->setDelta($delta)
            ->setSource($source);

        $em->persist($ledger);
    }

    private function guessDriverPhoto(?User $driver): ?string
    {
        if (!$driver) {
            return null;
        }
        $pseudo = strtolower((string)$driver->getPseudo());
        if (str_contains($pseudo, 'martin') || str_contains($pseudo, 'max')) {
            return '/images/Martin.jpg';
        }
        if (str_contains($pseudo, 'andrea') || str_contains($pseudo, 'anne')) {
            return '/images/Andrea.jpg';
        }
        return ($driver->getId() ?? 0) % 2 === 0 ? '/images/Andrea.jpg' : '/images/Martin.jpg';
    }
}
