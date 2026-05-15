<?php

namespace App\Repository;

use App\Entity\Round;
use App\Entity\Tournament;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Round>
 */
class RoundRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Round::class);
    }

    /** @return Round[] */
    public function findByTournament(Tournament $tournament): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.tournament = :t')
            ->setParameter('t', $tournament)
            ->orderBy('r.roundOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
