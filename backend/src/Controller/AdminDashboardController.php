<?php

namespace App\Controller;

use App\Entity\Ride;
use App\Entity\RideParticipant;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/admin', name: 'api_admin_')]
class AdminDashboardController extends AbstractController
{
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

        return $this->json([
            'series' => array_values($months),
            'users'  => $userList,
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
}
