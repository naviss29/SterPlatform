<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        // createClient() doit être appelé en premier pour booter le kernel
        $this->client = static::createClient();

        $this->em()->getConnection()->executeStatement('DELETE FROM refresh_tokens');
        $this->em()->getConnection()->executeStatement('DELETE FROM users');
    }

    // ------------------------------------------------------------------ helpers

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function hasher(): UserPasswordHasherInterface
    {
        return static::getContainer()->get(UserPasswordHasherInterface::class);
    }

    private function createVerifiedUser(string $email = 'alan@example.com', string $password = 'password123'): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword($this->hasher()->hashPassword($user, $password));
        $user->setIsVerified(true);

        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    private function createUnverifiedUser(string $email = 'unverified@example.com'): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword($this->hasher()->hashPassword($user, 'password123'));
        $user->setVerificationToken('valid_verification_token_abc123');
        $user->setVerificationTokenExpiresAt(new \DateTimeImmutable('+24 hours'));

        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    private function getJwtToken(string $email = 'alan@example.com', string $password = 'password123'): string
    {
        $this->client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'password' => $password,
        ]));

        $data = json_decode($this->client->getResponse()->getContent(), true);
        return $data['token'];
    }

    private function getRefreshToken(string $email = 'alan@example.com', string $password = 'password123'): string
    {
        $this->client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'password' => $password,
        ]));

        $data = json_decode($this->client->getResponse()->getContent(), true);
        return $data['refresh_token'];
    }

    // ------------------------------------------------------------------ register

    public function testRegisterSuccess(): void
    {
        $this->client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'nouveau@example.com',
            'password' => 'password123',
        ]));

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $data);
    }

    public function testRegisterWithInvalidEmail(): void
    {
        $this->client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'not-an-email',
            'password' => 'password123',
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testRegisterWithShortPassword(): void
    {
        $this->client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'alan@example.com',
            'password' => 'short',
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testRegisterWithExistingEmailReturns201(): void
    {
        $this->createVerifiedUser('alan@example.com');

        $this->client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'alan@example.com',
            'password' => 'password123',
        ]));

        // Même réponse 201 pour éviter l'énumération d'emails
        $this->assertResponseStatusCodeSame(201);
    }

    // ------------------------------------------------------------------ login

    public function testLoginSuccess(): void
    {
        $this->createVerifiedUser();

        $this->client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'alan@example.com',
            'password' => 'password123',
        ]));

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $data);
        $this->assertNotEmpty($data['token']);
    }

    public function testLoginWithWrongPassword(): void
    {
        $this->createVerifiedUser();

        $this->client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'alan@example.com',
            'password' => 'wrong_password',
        ]));

        $this->assertResponseStatusCodeSame(401);
    }

    public function testLoginWithUnknownEmail(): void
    {
        $this->client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'nobody@example.com',
            'password' => 'password123',
        ]));

        $this->assertResponseStatusCodeSame(401);
    }

    // ------------------------------------------------------------------ verify

    public function testVerifySuccess(): void
    {
        $this->createUnverifiedUser();

        $this->client->request('GET', '/api/auth/verify?token=valid_verification_token_abc123');

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('activé', $data['message']);
    }

    public function testVerifyWithInvalidToken(): void
    {
        $this->client->request('GET', '/api/auth/verify?token=invalid_token_xyz');

        $this->assertResponseStatusCodeSame(400);
    }

    public function testVerifyWithExpiredToken(): void
    {
        $user = new User();
        $user->setEmail('expired@example.com');
        $user->setPassword($this->hasher()->hashPassword($user, 'password123'));
        $user->setVerificationToken('expired_token_xyz');
        $user->setVerificationTokenExpiresAt(new \DateTimeImmutable('-1 hour'));
        $this->em()->persist($user);
        $this->em()->flush();

        $this->client->request('GET', '/api/auth/verify?token=expired_token_xyz');

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('expiré', $data['error']);
    }

    // ------------------------------------------------------------------ forgot-password

    public function testForgotPasswordSuccess(): void
    {
        $this->createVerifiedUser();

        $this->client->request('POST', '/api/auth/forgot-password', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'alan@example.com',
        ]));

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $data);
    }

    public function testForgotPasswordWithUnknownEmailReturns200(): void
    {
        $this->client->request('POST', '/api/auth/forgot-password', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'nobody@example.com',
        ]));

        // Même réponse pour éviter l'énumération d'emails
        $this->assertResponseIsSuccessful();
    }

    // ------------------------------------------------------------------ reset-password

    public function testResetPasswordSuccess(): void
    {
        $user = $this->createVerifiedUser();
        $token = 'valid_reset_token_abc123';
        $user->setResetToken($token);
        $user->setResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
        $this->em()->flush();

        $this->client->request('POST', '/api/auth/reset-password', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'token' => $token,
            'password' => 'newpassword456',
        ]));

        $this->assertResponseIsSuccessful();
    }

    public function testResetPasswordWithExpiredToken(): void
    {
        $user = $this->createVerifiedUser();
        $token = 'expired_reset_token';
        $user->setResetToken($token);
        $user->setResetTokenExpiresAt(new \DateTimeImmutable('-1 hour'));
        $this->em()->flush();

        $this->client->request('POST', '/api/auth/reset-password', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'token' => $token,
            'password' => 'newpassword456',
        ]));

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('expiré', $data['error']);
    }

    public function testResetPasswordWithShortPassword(): void
    {
        $this->client->request('POST', '/api/auth/reset-password', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'token' => 'any_token',
            'password' => 'short',
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    // ------------------------------------------------------------------ me

    public function testMeSuccess(): void
    {
        $this->createVerifiedUser();
        $token = $this->getJwtToken();

        $this->client->request('GET', '/api/auth/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('alan@example.com', $data['email']);
        $this->assertTrue($data['isVerified']);
        $this->assertContains('ROLE_USER', $data['roles']);
    }

    public function testMeUnauthorized(): void
    {
        $this->client->request('GET', '/api/auth/me');

        $this->assertResponseStatusCodeSame(401);
    }

    // ------------------------------------------------------------------ login returns refresh token

    public function testLoginReturnsRefreshToken(): void
    {
        $this->createVerifiedUser();

        $this->client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'alan@example.com',
            'password' => 'password123',
        ]));

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('refresh_token', $data);
        $this->assertNotEmpty($data['refresh_token']);
    }

    // ------------------------------------------------------------------ refresh

    public function testRefreshSuccess(): void
    {
        $this->createVerifiedUser();
        $refreshToken = $this->getRefreshToken();

        $this->client->request('POST', '/api/auth/refresh', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'refresh_token' => $refreshToken,
        ]));

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $data);
        $this->assertNotEmpty($data['token']);
    }

    public function testRefreshWithInvalidToken(): void
    {
        $this->client->request('POST', '/api/auth/refresh', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'refresh_token' => 'invalid_refresh_token_xyz',
        ]));

        $this->assertResponseStatusCodeSame(401);
    }

    public function testRefreshWithExpiredToken(): void
    {
        $this->createVerifiedUser();

        $this->em()->getConnection()->executeStatement(
            "INSERT INTO refresh_tokens (refresh_token, username, valid) VALUES (:token, :username, :valid)",
            [
                'token' => 'expired_refresh_token_abc',
                'username' => 'alan@example.com',
                'valid' => (new \DateTime('-1 hour'))->format('Y-m-d H:i:s'),
            ]
        );

        $this->client->request('POST', '/api/auth/refresh', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'refresh_token' => 'expired_refresh_token_abc',
        ]));

        $this->assertResponseStatusCodeSame(401);
    }

    // ------------------------------------------------------------------ logout

    public function testLogoutSuccess(): void
    {
        $this->createVerifiedUser();
        $jwtToken = $this->getJwtToken();
        $refreshToken = $this->getRefreshToken();

        $this->client->request('POST', '/api/auth/logout', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $jwtToken,
        ], json_encode(['refresh_token' => $refreshToken]));

        $this->assertResponseIsSuccessful();

        // Le refresh token est révoqué — le refresh doit maintenant échouer
        $this->client->request('POST', '/api/auth/refresh', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'refresh_token' => $refreshToken,
        ]));
        $this->assertResponseStatusCodeSame(401);
    }

    public function testLogoutWithoutJwtReturns401(): void
    {
        $this->client->request('POST', '/api/auth/logout', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'refresh_token' => 'any_token',
        ]));

        $this->assertResponseStatusCodeSame(401);
    }
}
