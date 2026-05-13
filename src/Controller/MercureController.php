<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\OrganizationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/mercure')]
class MercureController extends AbstractController
{
    public function __construct(
        private readonly OrganizationRepository $organizationRepository,
        private readonly TokenFactoryInterface $defaultTokenFactory,
    ) {}

    #[Route('/token', name: 'api_mercure_token', methods: ['GET'])]
    public function token(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $organizations = $this->organizationRepository->findByUser($user);

        $topics = [];
        foreach ($organizations as $org) {
            $topics[] = "orgs/{$org->getSlug()}";
            $topics[] = "orgs/{$org->getSlug()}/*";
        }

        $token = $this->defaultTokenFactory->create($topics, null, []);

        return $this->json(['token' => $token]);
    }
}
