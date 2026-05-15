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

class MercureControllerTest extends WebTestCase
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

    // ------------------------------------------------------------------ tests

    public function testTokenRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/mercure/token');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testTokenReturnsJwtForAuthenticatedUser(): void
    {
        $user = $this->createVerifiedUser();
        $this->createOrg('Acme Corp', $user);

        $jwt = $this->getJwtToken();

        $this->client->request('GET', '/api/mercure/token', [], [], [
            'HTTP_AUTHORIZATION' => "Bearer {$jwt}",
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $data);
        // Mercure subscriber token is a JWT — three dot-separated segments
        $this->assertSame(3, substr_count($data['token'], '.') + 1);
    }

    public function testTokenTopicsMatchUserOrganizations(): void
    {
        $user = $this->createVerifiedUser();
        $org1 = $this->createOrg('Alpha', $user);
        $org2 = $this->createOrg('Beta', $user);

        $jwt = $this->getJwtToken();

        $this->client->request('GET', '/api/mercure/token', [], [], [
            'HTTP_AUTHORIZATION' => "Bearer {$jwt}",
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        // Decode token payload (second segment)
        $payload = json_decode(base64_decode(str_pad(
            explode('.', $data['token'])[1],
            strlen(explode('.', $data['token'])[1]) + (4 - strlen(explode('.', $data['token'])[1]) % 4) % 4,
            '='
        )), true);

        $subscribedTopics = $payload['mercure']['subscribe'] ?? [];

        $this->assertContains("orgs/{$org1->getSlug()}", $subscribedTopics);
        $this->assertContains("orgs/{$org1->getSlug()}/*", $subscribedTopics);
        $this->assertContains("orgs/{$org2->getSlug()}", $subscribedTopics);
        $this->assertContains("orgs/{$org2->getSlug()}/*", $subscribedTopics);
    }

    public function testTokenForUserWithNoOrganizations(): void
    {
        $this->createVerifiedUser();
        $jwt = $this->getJwtToken();

        $this->client->request('GET', '/api/mercure/token', [], [], [
            'HTTP_AUTHORIZATION' => "Bearer {$jwt}",
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $data);

        $payload = json_decode(base64_decode(str_pad(
            explode('.', $data['token'])[1],
            strlen(explode('.', $data['token'])[1]) + (4 - strlen(explode('.', $data['token'])[1]) % 4) % 4,
            '='
        )), true);

        $this->assertEmpty($payload['mercure']['subscribe'] ?? []);
    }
}
