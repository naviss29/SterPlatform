<?php

namespace App\Controller;

use App\Entity\Organization;
use App\Entity\OrganizationMember;
use App\Entity\User;
use App\Enum\OrganizationRole;
use App\Repository\OrganizationMemberRepository;
use App\Repository\OrganizationRepository;
use App\Repository\UserRepository;
use App\Security\OrganizationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/api/organizations')]
class OrganizationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OrganizationRepository $organizationRepository,
        private readonly OrganizationMemberRepository $memberRepository,
        private readonly UserRepository $userRepository,
        private readonly SluggerInterface $slugger,
    ) {}

    #[Route('', name: 'api_organizations_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);
        $name = trim((string) ($data['name'] ?? ''));

        if (strlen($name) < 2) {
            return $this->json(['error' => 'Le nom doit contenir au moins 2 caractères.'], 400);
        }

        $slug = strtolower((string) $this->slugger->slug($name));

        if ($this->organizationRepository->findBySlug($slug)) {
            return $this->json(['error' => 'Ce nom est déjà pris.'], 409);
        }

        $organization = new Organization();
        $organization->setName($name);
        $organization->setSlug($slug);

        $member = new OrganizationMember();
        $member->setUser($user);
        $member->setOrganization($organization);
        $member->setRole(OrganizationRole::OWNER);

        $this->em->persist($organization);
        $this->em->persist($member);
        $this->em->flush();

        return $this->json($this->serializeOrg($organization, OrganizationRole::OWNER), 201);
    }

    #[Route('', name: 'api_organizations_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $organizations = $this->organizationRepository->findByUser($user);

        $data = array_map(function (Organization $org) use ($user) {
            $membership = $this->memberRepository->findMembership($user, $org);
            return $this->serializeOrg($org, $membership?->getRole());
        }, $organizations);

        return $this->json($data);
    }

    #[Route('/{slug}', name: 'api_organizations_get', methods: ['GET'])]
    public function get(string $slug): JsonResponse
    {
        $organization = $this->organizationRepository->findBySlug($slug);
        if (!$organization) {
            return $this->json(['error' => 'Organisation introuvable.'], 404);
        }

        $this->denyAccessUnlessGranted(OrganizationVoter::VIEW, $organization);

        /** @var User $user */
        $user = $this->getUser();
        $membership = $this->memberRepository->findMembership($user, $organization);

        return $this->json($this->serializeOrg($organization, $membership?->getRole()));
    }

    #[Route('/{slug}/members', name: 'api_organizations_add_member', methods: ['POST'])]
    public function addMember(string $slug, Request $request): JsonResponse
    {
        $organization = $this->organizationRepository->findBySlug($slug);
        if (!$organization) {
            return $this->json(['error' => 'Organisation introuvable.'], 404);
        }

        $this->denyAccessUnlessGranted(OrganizationVoter::MANAGE_MEMBERS, $organization);

        $data = json_decode($request->getContent(), true);
        $email = trim((string) ($data['email'] ?? ''));
        $roleValue = strtoupper((string) ($data['role'] ?? 'MEMBER'));

        $role = OrganizationRole::tryFrom($roleValue);
        if (!$role || $role === OrganizationRole::OWNER) {
            return $this->json(['error' => 'Rôle invalide. Valeurs acceptées : ADMIN, MEMBER.'], 400);
        }

        $target = $this->userRepository->findByEmail($email);
        if (!$target) {
            return $this->json(['error' => 'Utilisateur introuvable.'], 404);
        }

        if ($this->memberRepository->findMembership($target, $organization)) {
            return $this->json(['error' => 'Cet utilisateur est déjà membre.'], 409);
        }

        $member = new OrganizationMember();
        $member->setUser($target);
        $member->setOrganization($organization);
        $member->setRole($role);

        $this->em->persist($member);
        $this->em->flush();

        return $this->json([
            'email'    => $target->getEmail(),
            'role'     => $role->value,
            'joinedAt' => $member->getJoinedAt()->format(\DateTimeInterface::ATOM),
        ], 201);
    }

    #[Route('/{slug}/members', name: 'api_organizations_list_members', methods: ['GET'])]
    public function listMembers(string $slug): JsonResponse
    {
        $organization = $this->organizationRepository->findBySlug($slug);
        if (!$organization) {
            return $this->json(['error' => 'Organisation introuvable.'], 404);
        }

        $this->denyAccessUnlessGranted(OrganizationVoter::VIEW, $organization);

        $members = array_map(fn (OrganizationMember $m) => [
            'email'    => $m->getUser()->getEmail(),
            'role'     => $m->getRole()->value,
            'joinedAt' => $m->getJoinedAt()->format(\DateTimeInterface::ATOM),
        ], $organization->getMembers()->toArray());

        return $this->json($members);
    }

    private function serializeOrg(Organization $org, ?OrganizationRole $role): array
    {
        return [
            'id'        => (string) $org->getId(),
            'name'      => $org->getName(),
            'slug'      => $org->getSlug(),
            'role'      => $role?->value,
            'createdAt' => $org->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
