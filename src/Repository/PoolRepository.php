<?php

namespace App\Repository;

use App\Entity\Pool;
use App\Entity\Tournament;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Pool>
 */
class PoolRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pool::class);
    }

    /** @return Pool[] */
    public function findWithPlayersByTournament(Tournament $tournament): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.players', 'pp')
            ->leftJoin('pp.registration', 'r')
            ->addSelect('pp', 'r')
            ->where('p.tournament = :t')
            ->setParameter('t', $tournament)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
