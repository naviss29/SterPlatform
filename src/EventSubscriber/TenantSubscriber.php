<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Enum\OrganizationRole;
use App\Repository\OrganizationMemberRepository;
use App\Repository\OrganizationRepository;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class TenantSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly OrganizationRepository $organizationRepository,
        private readonly OrganizationMemberRepository $memberRepository,
        private readonly EntityManagerInterface $em,
        private readonly TokenStorageInterface $tokenStorage,
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

        // Vérifier que l'utilisateur est authentifié
        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User) {
            return;
        }

        $organization = $this->organizationRepository->findBySlug($slug);
        if (!$organization) {
            return;
        }

        // Vérifier que l'utilisateur est bien membre de cette organisation
        if (!$this->memberRepository->hasRole($user, $organization, ...OrganizationRole::cases())) {
            return;
        }

        $this->tenantContext->setCurrentOrganization($organization);

        $filter = $this->em->getFilters()->enable('tenant_filter');
        $filter->setParameter('organization_id', (string) $organization->getId());
    }
}
