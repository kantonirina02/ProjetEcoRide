<?php

namespace App\Controller;

use App\Entity\Review;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/moderation', name: 'api_moderation_')]
class ModerationController extends AbstractController
{
    #[Route('/reviews', name: 'reviews_list', methods: ['GET'])]
    public function listReviews(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $uid = (int)($request->getSession()->get('user_id') ?? 0);
        if ($uid <= 0) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        /** @var User|null $moderator */
        $moderator = $em->getRepository(User::class)->find($uid);
        if (!$moderator || !$this->isGrantedForModeration($moderator)) {
            return $this->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $statusFilter = (string)$request->query->get('status', 'pending');
        $allowed = ['pending','approved','rejected','all'];
        if (!in_array($statusFilter, $allowed, true)) $statusFilter = 'pending';

        $qb = $em->createQueryBuilder()
            ->select('review', 'author', 'target', 'ride')
            ->from(Review::class, 'review')
            ->leftJoin('review.author', 'author')
            ->leftJoin('review.target', 'target')
            ->leftJoin('review.ride', 'ride')
            ->orderBy('review.createdAt', 'DESC');

        if ($statusFilter !== 'all') {
            $qb->andWhere('review.status = :s')->setParameter('s', $statusFilter);
        }

        /** @var Review[] $reviews */
        $reviews = $qb->getQuery()->getResult();

        $data = array_map(static function (Review $r): array {
            return [
                'id'        => $r->getId(),
                'status'    => $r->getStatus(),
                'rating'    => $r->getRating(),
                'comment'   => $r->getComment(),
                'createdAt' => $r->getCreatedAt()?->format('Y-m-d H:i'),
                'author'    => [
                    'id'     => $r->getAuthor()?->getId(),
                    'pseudo' => $r->getAuthor()?->getPseudo(),
                    'email'  => $r->getAuthor()?->getEmail(),
                ],
                'target'    => [
                    'id'     => $r->getTarget()?->getId(),
                    'pseudo' => $r->getTarget()?->getPseudo(),
                ],
                'ride'      => [
                    'id'     => $r->getRide()?->getId(),
                    'from'   => $r->getRide()?->getFromCity(),
                    'to'     => $r->getRide()?->getToCity(),
                    'startAt'=> $r->getRide()?->getStartAt()?->format('Y-m-d H:i'),
                ],
                'moderation' => [
                    'validatedAt' => $r->getValidatedAt()?->format('Y-m-d H:i'),
                    'validatedBy' => $r->getValidatedBy()?->getPseudo(),
                    'note'        => $r->getModerationNote(),
                ],
            ];
        }, $reviews);

        return $this->json(['reviews' => $data]);
    }

    #[Route('/reviews/{id<\d+>}/decision', name: 'reviews_decision', methods: ['POST'])]
    public function reviewDecision(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $uid = (int)($request->getSession()->get('user_id') ?? 0);
        if ($uid <= 0) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        /** @var User|null $moderator */
        $moderator = $em->getRepository(User::class)->find($uid);
        if (!$moderator || !$this->isGrantedForModeration($moderator)) {
            return $this->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        /** @var Review|null $review */
        $review = $em->getRepository(Review::class)->find($id);
        if (!$review) {
            return $this->json(['error' => 'Review not found'], Response::HTTP_NOT_FOUND);
        }

        $raw = $request->getContent() ?? '';
        try {
            $payload = $raw !== '' ? json_decode($raw, true, 512, \JSON_THROW_ON_ERROR) : [];
        } catch (\JsonException) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $action = strtolower((string)($payload['action'] ?? ''));
        if (!in_array($action, ['approve','reject'], true)) {
            return $this->json(['error' => 'Invalid action'], Response::HTTP_BAD_REQUEST);
        }

        $note = isset($payload['note']) ? trim((string)$payload['note']) : null;

        if ($action === 'approve') {
            $review->setStatus('approved');
        } else {
            $review->setStatus('rejected');
        }

        $review
            ->setValidatedAt(new DateTimeImmutable('now'))
            ->setValidatedBy($moderator)
            ->setModerationNote($note ?: null);

        $em->persist($review);
        $em->flush();

        return $this->json(['ok' => true, 'review' => ['id' => $review->getId(), 'status' => $review->getStatus()]]);
    }

    private function isGrantedForModeration(User $user): bool
    {
        $roles = $user->getRoles();
        return in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_EMPLOYEE', $roles, true);
    }
}
