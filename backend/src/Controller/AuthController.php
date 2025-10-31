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
    /** ---------------- Utils simples de validation ---------------- */
    private function isValidEmail(string $email): bool
    {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Exigences minimales côté backend 
     */
    private function isValidPassword(string $password): bool
    {
        return \strlen($password) >= 8;
    }

    /** ------------------------- LOGIN ----------------------------- */
    /**
     * POST /api/auth/login
     * Body: {"email":"...","password":"..."}
     * - Vérifie l’existence de l’utilisateur
     * - Vérifie le mot de passe via password_verify()
     * - Fallback DEV: si le hash en base n’est pas un hash (pas de $2y$), autorise une égalité simple
     * - Dépose la session (user_id, user_email, user_pseudo)
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

        $email = strtolower(trim((string)($data['email'] ?? '')));
        $password = (string)($data['password'] ?? '');

        if ($email === '' || $password === '') {
            return $this->json(['error' => 'email et password requis'], Response::HTTP_BAD_REQUEST);
        }

        /** @var User|null $user */
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur inconnu'], Response::HTTP_UNAUTHORIZED);
        }

        $stored = (string)($user->getPassword() ?? '');
        $isHash = str_starts_with($stored, '$2y$') || str_starts_with($stored, '$argon2');

        $ok = $isHash ? password_verify($password, $stored) : ($stored !== '' && hash_equals($stored, $password));
        if (!$ok) {
            return $this->json(['error' => 'Mot de passe invalide'], Response::HTTP_UNAUTHORIZED);
        }

        // Session
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

    /** -------------------------- ME ------------------------------- */
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

    /** ------------------------- LOGOUT ---------------------------- */
    /** POST /api/auth/logout */
    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $request->getSession()->invalidate();
        return $this->json(['ok' => true]);
    }

    /** ------------------------ SIGNUP/REGISTER -------------------- */
    /**
     * POST /api/auth/signup    (alias historique conservé)
     * POST /api/auth/register  (nouvel alias REST)
     * Body: {"email":"...","password":"...","pseudo":"..."}
     * - Email format + unique
     * - Password >= 8 chars (BCrypt)
     * - Pseudo requis (sinon dérivé de l’email avant @)
     * - Auto-login après création
     */
    #[Route('/signup', name: 'signup', methods: ['POST'])]
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function signup(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $raw = $request->getContent() ?? '';
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->json(['error' => 'Corps JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $email  = strtolower(trim((string)($data['email']  ?? '')));
        $pass   = (string)($data['password']   ?? '');
        $pseudo = trim((string)($data['pseudo'] ?? ''));

        if ($email === '' || $pass === '') {
            return $this->json(['error' => 'email et password requis'], Response::HTTP_BAD_REQUEST);
        }
        if (!$this->isValidEmail($email)) {
            return $this->json(['error' => 'email invalide'], Response::HTTP_BAD_REQUEST);
        }
        if (!$this->isValidPassword($pass)) {
            return $this->json(['error' => 'mot de passe trop court (>= 8)'], Response::HTTP_BAD_REQUEST);
        }
        if ($em->getRepository(User::class)->findOneBy(['email' => $email])) {
            return $this->json(['error' => 'email déjà utilisé'], Response::HTTP_CONFLICT);
        }

        $user = (new User())
            ->setEmail($email)
            ->setPassword(password_hash($pass, PASSWORD_BCRYPT))
            ->setPseudo($pseudo !== '' ? $pseudo : explode('@', $email)[0])
            ->setRoles(['ROLE_USER'])
            ->setCreatedAt(new \DateTimeImmutable());

        $em->persist($user);
        $em->flush();

        // connexion auto
        $s = $request->getSession();
        $s->set('user_id',     $user->getId());
        $s->set('user_email',  $user->getEmail());
        $s->set('user_pseudo', $user->getPseudo());

        return $this->json([
            'ok'   => true,
            'user' => [
                'id'     => $user->getId(),
                'email'  => $user->getEmail(),
                'pseudo' => $user->getPseudo(),
            ],
        ], Response::HTTP_CREATED);
    }
}
