<?php

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\OrganizationMember;
use App\Entity\User;
use App\Enum\OrganizationRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrganizationMember>
 */
class OrganizationMemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrganizationMember::class);
    }

    public function findMembership(User $user, Organization $organization): ?OrganizationMember
    {
        return $this->findOneBy(['user' => $user, 'organization' => $organization]);
    }

    public function hasRole(User $user, Organization $organization, OrganizationRole ...$roles): bool
    {
        $membership = $this->findMembership($user, $organization);
        if (!$membership) {
            return false;
        }
        return in_array($membership->getRole(), $roles, true);
    }

    /**
     * Returns all memberships for a user with their organizations JOIN FETCHed.
     * Replaces findByUser + N×findMembership in list endpoints.
     *
     * @return OrganizationMember[]
     */
    public function findByUserWithOrganization(User $user): array
    {
        return $this->createQueryBuilder('m')
            ->select('m', 'o')
            ->innerJoin('m.organization', 'o')
            ->where('m.user = :user')
            ->setParameter('user', $user)
            ->orderBy('o.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns all memberships for an org with their users JOIN FETCHed.
     * Replaces $org->getMembers() + N×getUser() in listMembers.
     *
     * @return OrganizationMember[]
     */
    public function findByOrganizationWithUser(Organization $organization): array
    {
        return $this->createQueryBuilder('m')
            ->select('m', 'u')
            ->innerJoin('m.user', 'u')
            ->where('m.organization = :organization')
            ->setParameter('organization', $organization)
            ->orderBy('m.joinedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Verifies membership and returns it with organization JOIN FETCHed — in one query.
     * Replaces findBySlug + hasRole in TenantSubscriber.
     */
    public function findMembershipByOrgSlug(User $user, string $slug): ?OrganizationMember
    {
        return $this->createQueryBuilder('m')
            ->select('m', 'o')
            ->innerJoin('m.organization', 'o')
            ->where('o.slug = :slug')
            ->andWhere('m.user = :user')
            ->setParameter('slug', $slug)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
