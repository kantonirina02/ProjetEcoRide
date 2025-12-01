<?php

namespace App\Controller;

use App\Document\SearchLog;
use App\Entity\Ride;
use App\Entity\RideParticipant;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/admin', name: 'api_admin_')]
class AdminDashboardController extends AbstractController
{
    private const PLATFORM_FEE = 2;
    public function __construct(private readonly ?DocumentManager $documentManager = null) {}

    #[Route('/metrics', name: 'metrics', methods: ['GET'])]
    public function metrics(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $uid = (int)($request->getSession()->get('user_id') ?? 0);
        if ($uid <= 0) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        /** @var User|null $admin */
        $admin = $em->getRepository(User::class)->find($uid);
        if (!$admin || !in_array('ROLE_ADMIN', $admin->getRoles(), true)) {
            return $this->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        // Fenêtre : 6 derniers mois (inclus)
        $start = (new DateTimeImmutable('first day of this month'))->modify('-5 months');
        $cursor = clone $start;
        $revenueStart = (new DateTimeImmutable("today"))->modify("-13 days");
        $dayCursor = $revenueStart;

        $months = [];
        for ($i = 0; $i < 6; $i++) {
            $key = $cursor->format('Y-m');
            $months[$key] = [
                'label'    => $cursor->format('M Y'),
                'rides'    => 0,
                'bookings' => 0,
                'signups'  => 0,
            ];
            $cursor = $cursor->modify('+1 month');
        }

        // Compteurs journaliers (14 derniers jours)
        $dailyRides = [];
        $dayCursor = $revenueStart;
        for ($i = 0; $i < 14; $i++) {
            $key = $dayCursor->format('Y-m-d');
            $dailyRides[$key] = [
                'date'  => $key,
                'label' => $dayCursor->format('d/m'),
                'count' => 0,
            ];
            $dayCursor = $dayCursor->modify('+1 day');
        }

        $rides = $em->createQueryBuilder()
            ->select('r')
            ->from(Ride::class, 'r')
            ->where('r.startAt >= :start')
            ->setParameter('start', $start)
            ->getQuery()
            ->getResult();

        foreach ($rides as $ride) {
            if (!$ride instanceof Ride) {
                continue;
            }
            $date = $ride->getStartAt();
            if ($date instanceof DateTimeImmutable) {
                $key = $date->format('Y-m');
                if (isset($months[$key])) {
                    $months[$key]['rides']++;
                }
                $dayKey = $date->format('Y-m-d');
                if (isset($dailyRides[$dayKey]) && $date >= $revenueStart) {
                    $dailyRides[$dayKey]['count']++;
                }
            }
        }

        $participants = $em->createQueryBuilder()
            ->select('p')
            ->from(RideParticipant::class, 'p')
            ->where('p.requestedAt >= :start')
            ->setParameter('start', $start)
            ->getQuery()
            ->getResult();

        foreach ($participants as $participant) {
            if (!$participant instanceof RideParticipant) {
                continue;
            }
            $date = $participant->getRequestedAt();
            if ($date instanceof DateTimeImmutable) {
                $key = $date->format('Y-m');
                if (isset($months[$key])) {
                    $months[$key]['bookings']++;
                }
            }
        }

        $usersSignup = $em->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.createdAt >= :start')
            ->setParameter('start', $start)
            ->getQuery()
            ->getResult();

        foreach ($usersSignup as $u) {
            if (!$u instanceof User) {
                continue;
            }
            $created = $u->getCreatedAt();
            if ($created instanceof DateTimeImmutable) {
                $key = $created->format('Y-m');
                if (isset($months[$key])) {
                    $months[$key]['signups']++;
                }
            }
        }

        // Liste des comptes (pour la table Admin)
        $users = $em->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()->getResult();

        $userList = array_map(static function (User $user): array {
            return [
                'id'               => $user->getId(),
                'email'            => $user->getEmail(),
                'pseudo'           => $user->getPseudo(),
                'roles'            => $user->getRoles(),
                'credits'          => $user->getCreditsBalance(),
                'createdAt'        => $user->getCreatedAt()?->format('Y-m-d'),
                'suspended'        => $user->getSuspendedAt() !== null,
                'suspensionReason' => $user->getSuspensionReason(),
            ];
        }, $users);

        // Revenus plateforme (14 derniers jours)
        $revenueStart = (new DateTimeImmutable('today'))->modify('-13 days');
        $dayCursor = $revenueStart;
        $dailyRevenue = [];
        for ($i = 0; $i < 14; $i++) {
            $key = $dayCursor->format('Y-m-d');
            $dailyRevenue[$key] = [
                'date'    => $key,
                'label'   => $dayCursor->format('d/m'),
                'credits' => 0,
            ];
            $dayCursor = $dayCursor->modify('+1 day');
        }

        $periodRevenueTotal = 0;
        $participantsRevenue = $em->createQueryBuilder()
            ->select('p')
            ->from(RideParticipant::class, 'p')
            ->where('p.status = :confirmed')
            ->andWhere('p.confirmedAt >= :start')
            ->setParameter('confirmed', 'confirmed')
            ->setParameter('start', $revenueStart)
            ->getQuery()
            ->getResult();

        foreach ($participantsRevenue as $participant) {
            if (!$participant instanceof RideParticipant) {
                continue;
            }
            $confirmedAt = $participant->getConfirmedAt();
            if (!$confirmedAt) {
                continue;
            }
            $key = $confirmedAt->format('Y-m-d');
            $periodRevenueTotal += self::PLATFORM_FEE;
            if (isset($dailyRevenue[$key])) {
                $dailyRevenue[$key]['credits'] += self::PLATFORM_FEE;
            }
        }

        $ridesFees = $em->createQueryBuilder()
            ->select('r')
            ->from(Ride::class, 'r')
            ->where('r.createdAt >= :start')
            ->setParameter('start', $revenueStart)
            ->getQuery()
            ->getResult();

        foreach ($ridesFees as $ride) {
            if (!$ride instanceof Ride) {
                continue;
            }
            $createdAt = $ride->getCreatedAt();
            if (!$createdAt) {
                continue;
            }
            $key = $createdAt->format('Y-m-d');
            $periodRevenueTotal += self::PLATFORM_FEE;
            if (isset($dailyRevenue[$key])) {
                $dailyRevenue[$key]['credits'] += self::PLATFORM_FEE;
            }
        }

        $totalRides = (int)$em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Ride::class, 'r')
            ->getQuery()
            ->getSingleScalarResult();

        $totalConfirmed = (int)$em->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(RideParticipant::class, 'p')
            ->where('p.status = :status')
            ->setParameter('status', 'confirmed')
            ->getQuery()
            ->getSingleScalarResult();

        $platformTotal = ($totalRides * self::PLATFORM_FEE) + ($totalConfirmed * self::PLATFORM_FEE);

        return $this->json([
            'series'                 => array_values($months),
            'users'                  => $userList,
            'dailyRideCounts'        => array_values($dailyRides),
            'revenueDays'            => array_values($dailyRevenue),
            'periodRevenue'          => $periodRevenueTotal,
            'platformTotalCredits'   => $platformTotal,
        ]);
    }

    #[Route('/users/{id<\d+>}/suspend', name: 'user_suspend', methods: ['POST'])]
    public function suspendUser(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $uid = (int)($request->getSession()->get('user_id') ?? 0);
        if ($uid <= 0) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }
        /** @var User|null $admin */
        $admin = $em->getRepository(User::class)->find($uid);
        if (!$admin || !in_array('ROLE_ADMIN', $admin->getRoles(), true)) {
            return $this->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        /** @var User|null $user */
        $user = $em->getRepository(User::class)->find($id);
        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $raw = $request->getContent() ?? '';
        try {
            $payload = $raw !== '' ? json_decode($raw, true, 512, \JSON_THROW_ON_ERROR) : [];
        } catch (\JsonException) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $suspend = array_key_exists('suspend', $payload) ? (bool)$payload['suspend'] : true;
        $reason  = isset($payload['reason']) ? trim((string)$payload['reason']) : null;

        if ($suspend) {
            $user->setSuspendedAt(new DateTimeImmutable('now'));
            $user->setSuspensionReason($reason ?: 'Suspension sans motif');
        } else {
            $user->setSuspendedAt(null);
            $user->setSuspensionReason(null);
        }

        $em->persist($user);
        $em->flush();

        return $this->json(['ok' => true, 'suspended' => $user->getSuspendedAt() !== null]);
    }

    #[Route('/users', name: 'user_create', methods: ['POST'])]
    public function createEmployee(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $uid = (int)($request->getSession()->get('user_id') ?? 0);
        if ($uid <= 0) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        /** @var User|null $admin */
        $admin = $em->getRepository(User::class)->find($uid);
        if (!$admin || !in_array('ROLE_ADMIN', $admin->getRoles(), true)) {
            return $this->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $raw = $request->getContent() ?? '';
        try {
            $payload = $raw !== '' ? json_decode($raw, true, 512, \JSON_THROW_ON_ERROR) : [];
        } catch (\JsonException) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $email = strtolower(trim((string)($payload['email'] ?? '')));
        $pseudo = trim((string)($payload['pseudo'] ?? ''));
        $password = (string)($payload['password'] ?? '');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'email invalide'], Response::HTTP_BAD_REQUEST);
        }
        if ($pseudo === '') {
            return $this->json(['error' => 'pseudo requis'], Response::HTTP_BAD_REQUEST);
        }
        if (strlen($password) < 8) {
            return $this->json(['error' => 'mot de passe trop court (8 caractères min)'], Response::HTTP_BAD_REQUEST);
        }

        if ($em->getRepository(User::class)->findOneBy(['email' => $email])) {
            return $this->json(['error' => 'email déjà utilisé'], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user
            ->setEmail($email)
            ->setPseudo($pseudo)
            ->setRoles(['ROLE_USER', 'ROLE_EMPLOYEE']);

        $user->setPassword($passwordHasher->hashPassword($user, $password));

        $em->persist($user);
        $em->flush();

        return $this->json([
            'ok'   => true,
            'user' => [
                'id'     => $user->getId(),
                'email'  => $user->getEmail(),
                'pseudo' => $user->getPseudo(),
                'roles'  => $user->getRoles(),
            ],
        ], Response::HTTP_CREATED);
    }

    #[Route('/search-logs', name: 'search_logs', methods: ['GET'])]
    public function searchLogs(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $uid = (int)($request->getSession()->get('user_id') ?? 0);
        if ($uid <= 0) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        /** @var User|null $admin */
        $admin = $em->getRepository(User::class)->find($uid);
        if (!$admin || !in_array('ROLE_ADMIN', $admin->getRoles(), true)) {
            return $this->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        if (!$this->documentManager) {
            return $this->json(['logs' => []]);
        }

        try {
            $logs = $this->documentManager
                ->createQueryBuilder(SearchLog::class)
                ->sort('createdAt', 'DESC')
                ->limit(50)
                ->getQuery()
                ->execute();

            $data = [];
            foreach ($logs as $log) {
                if (!$log instanceof SearchLog) {
                    continue;
                }
                $data[] = [
                    'from'        => $log->getFrom(),
                    'to'          => $log->getTo(),
                    'date'        => $log->getDate(),
                    'results'     => $log->getResultCount(),
                    'userId'      => $log->getUserId(),
                    'clientIp'    => $log->getClientIp(),
                    'userAgent'   => $log->getUserAgent(),
                    'createdAt'   => $log->getCreatedAt()?->format('Y-m-d H:i'),
                ];
            }

            return $this->json(['logs' => $data]);
        } catch (\Throwable) {
            return $this->json(['logs' => []]);
        }
    }
}
