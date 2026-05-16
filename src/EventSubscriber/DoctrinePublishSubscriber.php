<?php

namespace App\EventSubscriber;

use App\Service\MercurePublisher;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class DoctrinePublishSubscriber implements EventSubscriber
{
    public function __construct(private readonly MercurePublisher $publisher) {}

    public function getSubscribedEvents(): array
    {
        return [Events::postPersist, Events::postUpdate, Events::postRemove];
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->publishEvent($args, 'created');
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->publishEvent($args, 'updated');
    }

    public function postRemove(LifecycleEventArgs $args): void
    {
        $this->publishEvent($args, 'deleted');
    }

    private function publishEvent(LifecycleEventArgs $args, string $action): void
    {
        $entity = $args->getObject();

        if (!method_exists($entity, 'getOrganization')) {
            return;
        }

        $organization = $entity->getOrganization();
        if ($organization === null) {
            return;
        }

        $entityType = strtolower((new \ReflectionClass($entity))->getShortName());
        $data = ['action' => $action, 'id' => method_exists($entity, 'getId') ? (string) $entity->getId() : null];

        try {
            $tournamentId = $this->extractTournamentId($entity);
            if ($tournamentId !== null) {
                $this->publisher->publishWithTournamentTopic($organization->getSlug(), $tournamentId, $entityType, $data);
            } else {
                $this->publisher->publishToOrganization($organization->getSlug(), $entityType, $data);
            }
        } catch (\Throwable) {
            // Hub unreachable — don't fail the DB transaction
        }
    }

    private function extractTournamentId(object $entity): ?string
    {
        if (method_exists($entity, 'getTournament')) {
            $tournament = $entity->getTournament();
            return $tournament?->getId() ? (string) $tournament->getId() : null;
        }
        if (method_exists($entity, 'getMatch')) {
            $match = $entity->getMatch();
            if ($match && method_exists($match, 'getTournament')) {
                $tournament = $match->getTournament();
                return $tournament?->getId() ? (string) $tournament->getId() : null;
            }
        }
        return null;
    }
}
