<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HealthControllerTest extends WebTestCase
{
    public function testHealthReturnsOkWhenDatabaseIsUp(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health');

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertSame('ok', $data['status']);
        $this->assertSame('ok', $data['checks']['database']);
    }

    public function testHealthMercureIsNonBlocking(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health');

        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertSame('ok', $data['status']);
        $this->assertArrayHasKey('mercure', $data['checks']);
        $this->assertContains($data['checks']['mercure'], ['ok', 'unreachable', 'unconfigured']);
    }
}
