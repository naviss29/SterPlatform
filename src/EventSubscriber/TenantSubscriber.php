<?php

namespace App\EventSubscriber;

use App\Repository\OrganizationRepository;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class TenantSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly OrganizationRepository $organizationRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 5],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $slug = $event->getRequest()->headers->get('X-Organization-Slug');
        if (!$slug) {
            return;
        }

        $organization = $this->organizationRepository->findBySlug($slug);
        if (!$organization) {
            return;
        }

        $this->tenantContext->setCurrentOrganization($organization);

        $filter = $this->em->getFilters()->enable('tenant_filter');
        $filter->setParameter('organization_id', (string) $organization->getId());
    }
}
