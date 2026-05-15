<?php

namespace App\Tests\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PromoteUserCommandTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $client = static::createClient();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->executeStatement('DELETE FROM organization_members');
        $this->em->getConnection()->executeStatement('DELETE FROM refresh_tokens');
        $this->em->getConnection()->executeStatement('DELETE FROM users');

        $application = new Application(static::$kernel);
        $this->commandTester = new CommandTester($application->find('app:user:promote'));
    }

    private function createUser(string $email, array $roles = []): User
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User();
        $user->setEmail($email);
        $user->setPassword($hasher->hashPassword($user, 'password123'));
        $user->setIsVerified(true);
        $user->setRoles($roles);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    public function testPromoteUser(): void
    {
        $this->createUser('alan@example.com');

        $this->commandTester->execute(['email' => 'alan@example.com']);

        $this->commandTester->assertCommandIsSuccessful();

        $this->em->clear();
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'alan@example.com']);
        $this->assertContains('ROLE_ADMIN', $user->getRoles());
    }

    public function testPromoteAlreadyAdminUser(): void
    {
        $this->createUser('admin@example.com', ['ROLE_ADMIN']);

        $this->commandTester->execute(['email' => 'admin@example.com']);

        $this->commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('déjà ROLE_ADMIN', $this->commandTester->getDisplay());
    }

    public function testPromoteNonExistentUser(): void
    {
        $this->commandTester->execute(['email' => 'unknown@example.com']);

        $this->assertSame(1, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('introuvable', $this->commandTester->getDisplay());
    }
}
