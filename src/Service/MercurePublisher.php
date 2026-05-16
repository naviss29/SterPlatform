<?php

namespace App\Service;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class MercurePublisher
{
    public function __construct(private readonly HubInterface $hub) {}

    public function publishToOrganization(string $orgSlug, string $entityType, array $data, ?string $id = null): void
    {
        $topics = [
            "orgs/{$orgSlug}",
            "orgs/{$orgSlug}/{$entityType}",
        ];

        $update = new Update(
            topics: $topics,
            data: json_encode(['type' => $entityType, 'data' => $data]),
            id: $id,
        );

        $this->hub->publish($update);
    }
}
