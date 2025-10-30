<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/auth', name: 'api_auth_')]
class AuthController extends AbstractController
{
    /**
     * POST /api/auth/login
     * Body: {"email":"user1@mail.test","password":"Passw0rd!"}
     * DEV/MOCK : on ne vérifie pas le hash, on dépose l’ID en session.
     */
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $raw = $request->getContent() ?? '';
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->json(['error' => 'Corps JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $email = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');
        if ($email === '' || $password === '') {
            return $this->json(['error' => 'email et password requis'], Response::HTTP_BAD_REQUEST);
        }

        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur inconnu'], Response::HTTP_UNAUTHORIZED);
        }

        $s = $request->getSession();
        $s->set('user_id',     $user->getId());
        $s->set('user_email',  $user->getEmail());
        $s->set('user_pseudo', $user->getPseudo());

        return $this->json([
            'ok' => true,
            'user' => [
                'id'     => $user->getId(),
                'email'  => $user->getEmail(),
                'pseudo' => $user->getPseudo(),
            ],
        ]);
    }

    /** GET /api/auth/me */
    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(Request $request): JsonResponse
    {
        $s = $request->getSession();
        $uid = (int)($s->get('user_id') ?? 0);

        return $this->json([
            'auth' => $uid > 0,
            'user' => $uid > 0 ? [
                'id'     => $uid,
                'email'  => $s->get('user_email'),
                'pseudo' => $s->get('user_pseudo'),
            ] : null,
        ]);
    }

    /** POST /api/auth/logout */
    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $request->getSession()->invalidate();
        return $this->json(['ok' => true]);
    }
}
