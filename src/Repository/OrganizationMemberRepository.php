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
}
