<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly MailerService $mailerService,
        private readonly RefreshTokenManagerInterface $refreshTokenManager,
    ) {}

    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(): never
    {
        // Intercepté par le firewall json_login — ce code n'est jamais atteint
        throw new \LogicException('This route is handled by the security firewall.');
    }

    #[Route('/refresh', name: 'api_auth_refresh', methods: ['POST'])]
    public function refresh(): never
    {
        // Intercepté par le firewall refresh_jwt — ce code n'est jamais atteint
        throw new \LogicException('This route is handled by the security firewall.');
    }

    #[Route('/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 180) {
            return $this->json(['error' => 'Email invalide.'], 400);
        }

        if (strlen($password) < 8 || strlen($password) > 4096) {
            return $this->json(['error' => 'Le mot de passe doit contenir entre 8 et 4096 caractères.'], 400);
        }

        if ($this->userRepository->findByEmail($email)) {
            // Réponse identique pour éviter l'énumération d'emails
            return $this->json(['message' => 'Si cet email est valide, un lien de confirmation vous a été envoyé.'], 201);
        }

        $token = bin2hex(random_bytes(32));

        $user = new User();
        $user->setEmail($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setVerificationToken($token);
        $user->setVerificationTokenExpiresAt(new \DateTimeImmutable('+24 hours'));

        $this->em->persist($user);
        $this->em->flush();

        $this->mailerService->sendVerificationEmail($user);

        return $this->json(['message' => 'Si cet email est valide, un lien de confirmation vous a été envoyé.'], 201);
    }

    #[Route('/verify', name: 'api_auth_verify', methods: ['GET'])]
    public function verify(Request $request): JsonResponse
    {
        $token = $request->query->getString('token');

        if (!$token) {
            return $this->json(['error' => 'Token manquant.'], 400);
        }

        $user = $this->userRepository->findByVerificationToken($token);

        if (!$user) {
            return $this->json(['error' => 'Token invalide ou déjà utilisé.'], 400);
        }

        if ($user->getVerificationTokenExpiresAt() < new \DateTimeImmutable()) {
            return $this->json(['error' => 'Le lien de confirmation a expiré. Veuillez vous réinscrire.'], 400);
        }

        $user->setIsVerified(true);
        $user->setVerificationToken(null);
        $user->setVerificationTokenExpiresAt(null);

        $this->em->flush();

        return $this->json(['message' => 'Compte activé avec succès. Vous pouvez maintenant vous connecter.']);
    }

    #[Route('/forgot-password', name: 'api_auth_forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = trim((string) ($data['email'] ?? ''));

        // Réponse identique dans tous les cas pour éviter l'énumération d'emails
        $genericResponse = $this->json(['message' => 'Si cet email est associé à un compte, un lien de réinitialisation vous a été envoyé.']);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $genericResponse;
        }

        $user = $this->userRepository->findByEmail($email);

        if (!$user || !$user->isVerified()) {
            return $genericResponse;
        }

        $token = bin2hex(random_bytes(32));
        $user->setResetToken($token);
        $user->setResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
        $this->em->flush();

        $this->mailerService->sendPasswordResetEmail($user);

        return $genericResponse;
    }

    #[Route('/reset-password', name: 'api_auth_reset_password', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $token = (string) ($data['token'] ?? '');
        $password = (string) ($data['password'] ?? '');

        if (!$token) {
            return $this->json(['error' => 'Token manquant.'], 400);
        }

        if (strlen($password) < 8 || strlen($password) > 4096) {
            return $this->json(['error' => 'Le mot de passe doit contenir entre 8 et 4096 caractères.'], 400);
        }

        $user = $this->userRepository->findByResetToken($token);

        if (!$user) {
            return $this->json(['error' => 'Token invalide ou déjà utilisé.'], 400);
        }

        if ($user->getResetTokenExpiresAt() < new \DateTimeImmutable()) {
            return $this->json(['error' => 'Le lien de réinitialisation a expiré.'], 400);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setResetToken(null);
        $user->setResetTokenExpiresAt(null);
        $this->em->flush();

        return $this->json(['message' => 'Mot de passe mis à jour avec succès.']);
    }

    #[Route('/logout', name: 'api_auth_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $tokenString = (string) ($data['refresh_token'] ?? '');

        if ($tokenString) {
            $refreshToken = $this->refreshTokenManager->get($tokenString);
            if ($refreshToken) {
                $this->refreshTokenManager->delete($refreshToken);
            }
        }

        return $this->json(['message' => 'Déconnecté avec succès.']);
    }

    #[Route('/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'id' => (string) $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'isVerified' => $user->isVerified(),
            'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }
}
