<?php

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\Tournament;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tournament>
 */
class TournamentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tournament::class);
    }

    /** @return Tournament[] */
    public function findByOrganization(Organization $organization): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.organization = :org')
            ->setParameter('org', $organization)
            ->orderBy('t.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findWithRounds(string $id): ?Tournament
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.rounds', 'r')
            ->addSelect('r')
            ->where('t.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
