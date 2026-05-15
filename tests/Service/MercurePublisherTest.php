<?php

namespace App\Tests\Service;

use App\Service\MercurePublisher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class MercurePublisherTest extends TestCase
{
    public function testPublishesToOrgTopics(): void
    {
        $hub = $this->createMock(HubInterface::class);

        $hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update) {
                $topics = $update->getTopics();
                $this->assertContains('orgs/acme', $topics);
                $this->assertContains('orgs/acme/project', $topics);

                $data = json_decode($update->getData(), true);
                $this->assertSame('project', $data['type']);
                $this->assertSame('created', $data['data']['action']);

                return true;
            }));

        $publisher = new MercurePublisher($hub);
        $publisher->publishToOrganization('acme', 'project', ['action' => 'created', 'id' => '123']);
    }

    public function testPublishUsesCustomId(): void
    {
        $hub = $this->createMock(HubInterface::class);

        $hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update) {
                $this->assertSame('custom-id-42', $update->getId());
                return true;
            }));

        $publisher = new MercurePublisher($hub);
        $publisher->publishToOrganization('acme', 'task', ['action' => 'updated'], 'custom-id-42');
    }
}
