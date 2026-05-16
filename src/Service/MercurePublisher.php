<?php

namespace App\Service;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class MercurePublisher
{
    public function __construct(private readonly HubInterface $hub) {}

    public function publishToOrganization(string $orgSlug, string $entityType, array $data, ?string $id = null): void
    {
        $this->publish(["orgs/{$orgSlug}", "orgs/{$orgSlug}/{$entityType}"], $entityType, $data, $id);
    }

    public function publishWithTournamentTopic(string $orgSlug, string $tournamentId, string $entityType, array $data, ?string $id = null): void
    {
        $this->publish([
            "orgs/{$orgSlug}",
            "orgs/{$orgSlug}/{$entityType}",
            "orgs/{$orgSlug}/tournaments/{$tournamentId}",
        ], $entityType, $data, $id);
    }

    private function publish(array $topics, string $entityType, array $data, ?string $id): void
    {
        $update = new Update(
            topics: $topics,
            data: json_encode(['type' => $entityType, 'data' => $data]),
            id: $id,
        );

        $this->hub->publish($update);
    }
}
