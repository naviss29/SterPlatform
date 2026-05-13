<?php

namespace App\Tests\Controller;

use App\Entity\Organization;
use App\Entity\OrganizationMember;
use App\Entity\User;
use App\Enum\OrganizationRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class OrganizationControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $this->em()->getConnection()->executeStatement('DELETE FROM organization_members');
        $this->em()->getConnection()->executeStatement('DELETE FROM organizations');
        $this->em()->getConnection()->executeStatement('DELETE FROM refresh_tokens');
        $this->em()->getConnection()->executeStatement('DELETE FROM users');
    }

    // ------------------------------------------------------------------ helpers

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function createVerifiedUser(string $email = 'alan@example.com', string $password = 'password123'): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword(
            static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, $password)
        );
        $user->setIsVerified(true);
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
        return json_decode($this->client->getResponse()->getContent(), true)['token'];
    }

    private function createOrg(string $name, User $owner): Organization
    {
        $org = new Organization();
        $org->setName($name);
        $org->setSlug(strtolower(str_replace(' ', '-', $name)));

        $member = new OrganizationMember();
        $member->setUser($owner);
        $member->setOrganization($org);
        $member->setRole(OrganizationRole::OWNER);

        $this->em()->persist($org);
        $this->em()->persist($member);
        $this->em()->flush();

        return $org;
    }

    // ------------------------------------------------------------------ POST /api/organizations

    public function testCreateOrganizationSuccess(): void
    {
        $this->createVerifiedUser();
        $token = $this->getJwtToken();

        $this->client->request('POST', '/api/organizations', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode(['name' => 'DartsOpen']));

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('DartsOpen', $data['name']);
        $this->assertSame('dartsopen', $data['slug']);
        $this->assertSame('OWNER', $data['role']);
    }

    public function testCreateOrganizationDuplicateSlug(): void
    {
        $user = $this->createVerifiedUser();
        $this->createOrg('DartsOpen', $user);
        $token = $this->getJwtToken();

        $this->client->request('POST', '/api/organizations', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode(['name' => 'DartsOpen']));

        $this->assertResponseStatusCodeSame(409);
    }

    public function testCreateOrganizationUnauthenticated(): void
    {
        $this->client->request('POST', '/api/organizations', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'DartsOpen']));

        $this->assertResponseStatusCodeSame(401);
    }

    // ------------------------------------------------------------------ GET /api/organizations

    public function testListOrganizationsReturnsOnlyUserOrgs(): void
    {
        $alan = $this->createVerifiedUser('alan@example.com');
        $bob  = $this->createVerifiedUser('bob@example.com');

        $this->createOrg('DartsOpen', $alan);
        $this->createOrg('FestManager', $bob);

        $token = $this->getJwtToken('alan@example.com');

        $this->client->request('GET', '/api/organizations', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame('DartsOpen', $data[0]['name']);
    }

    // ------------------------------------------------------------------ GET /api/organizations/{slug}

    public function testGetOrganizationSuccess(): void
    {
        $user = $this->createVerifiedUser();
        $this->createOrg('DartsOpen', $user);
        $token = $this->getJwtToken();

        $this->client->request('GET', '/api/organizations/dartsopen', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('DartsOpen', $data['name']);
        $this->assertSame('OWNER', $data['role']);
    }

    public function testGetOrganizationForbiddenForNonMember(): void
    {
        $alan = $this->createVerifiedUser('alan@example.com');
        $this->createVerifiedUser('bob@example.com');
        $this->createOrg('DartsOpen', $alan);

        $token = $this->getJwtToken('bob@example.com');

        $this->client->request('GET', '/api/organizations/dartsopen', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testGetOrganizationNotFound(): void
    {
        $this->createVerifiedUser();
        $token = $this->getJwtToken();

        $this->client->request('GET', '/api/organizations/unknown-org', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    // ------------------------------------------------------------------ POST /api/organizations/{slug}/members

    public function testAddMemberSuccess(): void
    {
        $owner = $this->createVerifiedUser('owner@example.com');
        $this->createVerifiedUser('new@example.com');
        $this->createOrg('DartsOpen', $owner);

        $token = $this->getJwtToken('owner@example.com');

        $this->client->request('POST', '/api/organizations/dartsopen/members', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode(['email' => 'new@example.com', 'role' => 'MEMBER']));

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('new@example.com', $data['email']);
        $this->assertSame('MEMBER', $data['role']);
    }

    public function testAddMemberForbiddenForSimpleMember(): void
    {
        $owner  = $this->createVerifiedUser('owner@example.com');
        $member = $this->createVerifiedUser('member@example.com');
        $this->createVerifiedUser('new@example.com');

        $org = $this->createOrg('DartsOpen', $owner);

        $m = new OrganizationMember();
        $m->setUser($member);
        $m->setOrganization($org);
        $m->setRole(OrganizationRole::MEMBER);
        $this->em()->persist($m);
        $this->em()->flush();

        $token = $this->getJwtToken('member@example.com');

        $this->client->request('POST', '/api/organizations/dartsopen/members', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode(['email' => 'new@example.com', 'role' => 'MEMBER']));

        $this->assertResponseStatusCodeSame(403);
    }

    public function testAddMemberCannotSetOwnerRole(): void
    {
        $owner = $this->createVerifiedUser('owner@example.com');
        $this->createVerifiedUser('new@example.com');
        $this->createOrg('DartsOpen', $owner);

        $token = $this->getJwtToken('owner@example.com');

        $this->client->request('POST', '/api/organizations/dartsopen/members', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode(['email' => 'new@example.com', 'role' => 'OWNER']));

        $this->assertResponseStatusCodeSame(400);
    }

    // ------------------------------------------------------------------ isolation

    public function testMemberCannotSeeOtherOrgDetails(): void
    {
        $alan = $this->createVerifiedUser('alan@example.com');
        $bob  = $this->createVerifiedUser('bob@example.com');

        $this->createOrg('DartsOpen', $alan);
        $this->createOrg('FestManager', $bob);

        // Alan essaie d'accéder à l'org de Bob
        $token = $this->getJwtToken('alan@example.com');
        $this->client->request('GET', '/api/organizations/festmanager', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(403);
    }
}
