<?php

namespace App\Repository;

use App\Entity\DartsMatch;
use App\Entity\MatchSet;
use App\Entity\Round;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MatchSet>
 */
class MatchSetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MatchSet::class);
    }

    public function findByMatchAndRound(DartsMatch $match, Round $round): ?MatchSet
    {
        return $this->findOneBy(['match' => $match, 'round' => $round]);
    }

    /** @return MatchSet[] */
    public function findByMatch(DartsMatch $match): array
    {
        return $this->createQueryBuilder('ms')
            ->leftJoin('ms.round', 'r')
            ->addSelect('r')
            ->where('ms.match = :m')
            ->setParameter('m', $match)
            ->orderBy('r.roundOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
