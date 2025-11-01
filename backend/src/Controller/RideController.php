<?php

namespace App\Controller;

use App\Entity\Brand;
use App\Entity\Ride;
use App\Entity\RideParticipant;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Repository\RideRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

// Validation manuelle (pas de dépendance symfony/validator requise)

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

    #[Route('/rides', name: 'rides_list', methods: ['GET'])]
    public function list(Request $req, RideRepository $repo): JsonResponse
    {
        $from        = $req->query->get('from');
        $to          = $req->query->get('to');
        $date        = $req->query->get('date');
        $eco         = $req->query->get('eco');
        $priceMax    = $req->query->get('priceMax');
        $durationMax = $req->query->get('durationMax'); // minutes

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
                   ->setParameter('s', $start)->setParameter('e', $end);
            } catch (\Exception $e) {
                return $this->json(['error' => 'Invalid date format, expected YYYY-MM-DD'], Response::HTTP_BAD_REQUEST);
            }
        }
        if ($eco !== null && $eco !== '') {
            $qb->andWhere('v.eco = :eco')->setParameter('eco', (bool)((int)$eco));
        }
        if ($priceMax !== null && $priceMax !== '') {
            // DECIMAL => pass as string
            $qb->andWhere('r.price <= :pmax')->setParameter('pmax', (string)$priceMax);
        }

        /** @var Ride[] $rides */
        $rides = $qb->getQuery()->getResult();

        // Filter durationMax in PHP
        if ($durationMax !== null && $durationMax !== '') {
            $max = (int)$durationMax;
            $rides = array_values(array_filter($rides, static function (Ride $r) use ($max) {
                $start = $r->getStartAt();
                $end   = $r->getEndAt();
                if (!$start || !$end) return false;
                $minutes = (int) round(($end->getTimestamp() - $start->getTimestamp()) / 60);
                return $minutes <= $max;
            }));
        }

        $data = array_map(static function (Ride $r): array {
            $vehicle = $r->getVehicle();
            $brand   = $vehicle?->getBrand();
            $driver  = $r->getDriver();
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
                'driver'     => $driver ? [
                    'id'     => $driver->getId(),
                    'pseudo' => $driver->getPseudo(),
                ] : null,
            ];
        }, $rides);

        return $this->json($data);
    }

    /**
     * Réservation par l'utilisateur connecté (session).
     * POST /api/rides/{id}/book
     * Body JSON (optionnel) : { "seats": 1 }
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

        // ❗ P0: interdire au conducteur de réserver son propre trajet
        if ($ride->getDriver() && $ride->getDriver()->getId() === $userId) {
            return $this->json(['error' => 'Driver cannot book own ride'], Response::HTTP_CONFLICT);
        }

        // ❗ P0: interdire la réservation si le trajet est passé / déjà démarré
        $now = new \DateTimeImmutable('now');
        if ($ride->getStartAt() && $ride->getStartAt() < $now) {
            return $this->json(['error' => 'Ride already started or past'], Response::HTTP_CONFLICT);
        }

        // Seats (default 1)
        $seats = 1;
        $raw = $req->getContent() ?? '';
        if ($raw !== '') {
            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded) && isset($decoded['seats'])) {
                    $seats = max(1, (int)$decoded['seats']);
                }
            } catch (\JsonException) { /* ignore */ }
        }
        if ($seats <= 0) {
            $formSeats = $req->request->get('seats') ?? $req->query->get('seats');
            if ($formSeats !== null) $seats = max(1, (int)$formSeats);
            if ($seats <= 0) $seats = 1;
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

            $userRef = $em->getReference(User::class, $userId);

            // Prevent double booking
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

        $userRef = $em->getReference(User::class, $userId);
        $rp = $em->getRepository(RideParticipant::class)->findOneBy(['ride' => $ride, 'user' => $userRef]);
        if (!$rp) {
            return $this->json(['error' => 'Not booked'], Response::HTTP_NOT_FOUND);
        }

        $seats = $rp->getSeatsBooked();
        $em->remove($rp);
        $ride->setSeatsLeft($ride->getSeatsLeft() + $seats);
        $em->persist($ride);
        $em->flush();

        return $this->json(['ok' => true, 'rideId' => $ride->getId(), 'seatsLeft' => $ride->getSeatsLeft()]);
    }

    /**
     * Mes réservations (session)
     * GET /api/me/bookings
     */
    #[Route('/me/bookings', name: 'my_bookings', methods: ['GET'])]
    public function myBookings(Request $req, EntityManagerInterface $em): JsonResponse
    {
        $uid = (int)($req->getSession()->get('user_id') ?? 0);
        if ($uid <= 0) {
            // 200 + auth=false (pratique côté front)
            return $this->json(['auth' => false, 'bookings' => []], Response::HTTP_OK);
        }
        $userRef = $em->getReference(User::class, $uid);
        $parts = $em->getRepository(RideParticipant::class)->findBy(['user' => $userRef], ['id' => 'DESC']);

        $out = [];
        foreach ($parts as $p) {
            $r = $p->getRide();
            $out[] = [
                'rideId'    => $r->getId(),
                'from'      => $r->getFromCity(),
                'to'        => $r->getToCity(),
                'startAt'   => $r->getStartAt()?->format('Y-m-d H:i'),
                'seats'     => $p->getSeatsBooked(),
                'status'    => $p->getStatus(),
            ];
        }

        return $this->json(['auth' => true, 'bookings' => $out]);
    }

    /**
     * Mes trajets en tant que conducteur (session)
     * GET /api/me/rides
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
     * Trajets d’un conducteur (pour /account)
     * GET /api/users/{id}/rides
     */
    #[Route('/users/{id<\d+>}/rides', name: 'user_rides', methods: ['GET'])]
    public function userRides(int $id, EntityManagerInterface $em): JsonResponse
    {
        $userRef = $em->getReference(User::class, $id);
        $rides = $em->getRepository(Ride::class)->findBy(
            ['driver' => $userRef],
            ['startAt' => 'DESC']
        );

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
     * GET /api/users/{id}/bookings
     */
    #[Route('/users/{id<\d+>}/bookings', name: 'user_bookings', methods: ['GET'])]
    public function userBookings(int $id, EntityManagerInterface $em): JsonResponse
    {
        $userRef = $em->getReference(User::class, $id);
        $parts = $em->getRepository(RideParticipant::class)->findBy(
            ['user' => $userRef],
            ['id' => 'DESC']
        );

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
     * POST /api/rides  (création d'un trajet)
     */
    #[Route('/rides', name: 'ride_create', methods: ['POST'])]
    public function createRide(
        Request $req,
        ManagerRegistry $doctrine
    ): JsonResponse {
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

        // Validation simple
        $errors = [];
        $isInt = static fn($v) => filter_var($v, FILTER_VALIDATE_INT) !== false;
        $isNum = static fn($v) => is_numeric($v);
        if (!isset($data['vehicle']) || !is_array($data['vehicle'])) $errors[] = 'vehicle manquant';
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

        // Vehicle
        $vehInput  = $data['vehicle'];
        $brandName = trim((string)($vehInput['brand'] ?? ''));
        if ($brandName === '') {
            return $this->json(['error' => 'vehicle.brand est requis'], Response::HTTP_BAD_REQUEST);
        }

        $brand = $em->getRepository(Brand::class)->findOneBy(['name' => $brandName]) ?? (new Brand())->setName($brandName);
        $em->persist($brand);

        $vehicle = (new Vehicle())
            ->setOwner($driver)
            ->setBrand($brand)
            ->setModel((string)($vehInput['model'] ?? ''))
            ->setEco((bool)($vehInput['eco'] ?? false))
            ->setSeatsTotal((int)($vehInput['seatsTotal'] ?? 4))
            ->setColor($vehInput['color'] ?? null)
            ->setEnergy((string)($vehInput['energy'] ?? 'electric'))
            ->setFirstRegistrationAt(new \DateTimeImmutable('2019-01-01'));

        $em->persist($vehicle);

        try {
            $start = new \DateTimeImmutable((string)$data['startAt']);
            $end   = new \DateTimeImmutable((string)$data['endAt']);
        } catch (\Exception) {
            return $this->json(['error' => 'Format datetime invalide (attendu: Y-m-d H:i)'], Response::HTTP_BAD_REQUEST);
        }

        // règles de cohérence sur les dates & prix
        $now = new \DateTimeImmutable('now');
        if ($start < $now) {
            return $this->json(['error' => 'startAt must be in the future'], Response::HTTP_BAD_REQUEST);
        }
        if ($end <= $start) {
            return $this->json(['error' => 'endAt must be after startAt'], Response::HTTP_BAD_REQUEST);
        }
        if ((float)$data['price'] <= 0) {
            return $this->json(['error' => 'price must be greater than 0'], Response::HTTP_BAD_REQUEST);
        }

        $ride = (new Ride())
            ->setDriver($driver)
            ->setVehicle($vehicle)
            ->setFromCity((string)$data['fromCity'])
            ->setToCity((string)$data['toCity'])
            ->setStartAt($start)
            ->setEndAt($end)
            ->setPrice((string)$data['price'])
            ->setSeatsTotal($vehicle->getSeatsTotal())
            ->setSeatsLeft($vehicle->getSeatsTotal())
            ->setStatus('open')
            ->setAllowSmoker((bool)($data['allowSmoker'] ?? false))
            ->setAllowAnimals((bool)($data['allowAnimals'] ?? false))
            ->setMusicStyle($data['musicStyle'] ?? null)
            ->setCreatedAt(new \DateTimeImmutable());

        $em->persist($ride);
        $em->flush();

        return $this->json([
            'id'        => $ride->getId(),
            'driverId'  => $driver->getId(),
            'vehicle'   => [
                'brand'  => $vehicle->getBrand()->getName(),
                'model'  => $vehicle->getModel(),
                'eco'    => $vehicle->isEco(),
                'energy' => $vehicle->getEnergy(),
                'seats'  => $vehicle->getSeatsTotal(),
            ],
            'from'      => $ride->getFromCity(),
            'to'        => $ride->getToCity(),
            'startAt'   => $ride->getStartAt()->format('Y-m-d H:i'),
            'endAt'     => $ride->getEndAt()->format('Y-m-d H:i'),
            'price'     => (float)$ride->getPrice(),
            'status'    => $ride->getStatus(),
        ], Response::HTTP_CREATED);
    }
       /**
 * Détail d’un trajet + participants
 * GET /api/rides/{id}
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
            'user'   => $u ? [
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
        'driver'     => $driver ? [
            'id'     => $driver->getId(),
            'pseudo' => $driver->getPseudo(),
            // email visible uniquement pour le conducteur lui-même
            'email'  => ($isDriverViewing && $sessionUserId === $driver->getId()) ? $driver->getEmail() : null,
        ] : null,
        'participants' => $participants,
    ]);
}

}
