<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Core\User\UserInterface;

#[Route('/api', name: 'api_')]
class AuthController extends AbstractController
{
    /**
     * Note: /api/auth/login est géré automatiquement par json_login (security.yaml).
     */
    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(?UserInterface $user = null): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }
        /** @var \App\Entity\User $user */
        return $this->json([
            'id'     => $user->getId(),
            'email'  => $user->getEmail(),
            'pseudo' => $user->getPseudo(),
            'roles'  => $user->getRoles(),
        ]);
    }
}
