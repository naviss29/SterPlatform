<?php

namespace App\Tests\Controller;

use App\Entity\EmailTemplate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EmailControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em()->getConnection()->executeStatement('DELETE FROM email_templates');
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function appToken(): string
    {
        return static::getContainer()->getParameter('env(APP_TOKEN)') ?? 'change_me_in_prod';
    }

    private function createTemplate(string $slug = 'test_template'): EmailTemplate
    {
        $template = new EmailTemplate();
        $template->setSlug($slug);
        $template->setProject('test');
        $template->setSubject('Bonjour {{ nom }}');
        $template->setHtmlBody('<p>Bienvenue {{ nom }}, ton tournoi est {{ tournoi }}.</p>');
        $template->setDescription('Variables : {{ nom }}, {{ tournoi }}');

        $this->em()->persist($template);
        $this->em()->flush();

        return $template;
    }

    // ------------------------------------------------------------------ tests

    public function testSendRequiresToken(): void
    {
        $this->client->request('POST', '/api/email/send', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['template' => 'test', 'to' => 'a@b.com', 'variables' => []]));

        $this->assertResponseStatusCodeSame(401);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testSendWithInvalidToken(): void
    {
        $this->client->request('POST', '/api/email/send', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-App-Token' => 'wrong_token',
        ], json_encode(['template' => 'test', 'to' => 'a@b.com', 'variables' => []]));

        $this->assertResponseStatusCodeSame(401);
    }

    public function testSendRequiresTemplateField(): void
    {
        $this->client->request('POST', '/api/email/send', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-App-Token' => 'change_me_in_prod',
        ], json_encode(['to' => 'a@b.com', 'variables' => []]));

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('template', $data['error']);
    }

    public function testSendRequiresValidEmail(): void
    {
        $this->client->request('POST', '/api/email/send', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-App-Token' => 'change_me_in_prod',
        ], json_encode(['template' => 'test', 'to' => 'not-an-email', 'variables' => []]));

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('to', $data['error']);
    }

    public function testSendReturns404ForUnknownTemplate(): void
    {
        $this->client->request('POST', '/api/email/send', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-App-Token' => 'change_me_in_prod',
        ], json_encode(['template' => 'inexistant', 'to' => 'a@b.com', 'variables' => []]));

        $this->assertResponseStatusCodeSame(404);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('inexistant', $data['error']);
    }

    public function testSendSuccess(): void
    {
        $this->createTemplate('test_template');

        $this->client->request('POST', '/api/email/send', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-App-Token' => 'change_me_in_prod',
        ], json_encode([
            'template'  => 'test_template',
            'to'        => 'destinataire@example.com',
            'variables' => ['nom' => 'Alan', 'tournoi' => 'Open de Brest'],
        ]));

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['sent']);
    }
}
