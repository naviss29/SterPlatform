<?php

namespace App\Repository;

use App\Entity\DartsMatch;
use App\Entity\Tournament;
use App\Enum\MatchStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DartsMatch>
 */
class DartsMatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DartsMatch::class);
    }

    /** @return DartsMatch[] */
    public function findByTournamentWithSets(Tournament $tournament): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.sets', 's')
            ->leftJoin('s.round', 'r')
            ->addSelect('s', 'r')
            ->where('m.tournament = :t')
            ->setParameter('t', $tournament)
            ->orderBy('m.boardNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return DartsMatch[] */
    public function findLiveByTournament(Tournament $tournament): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.sets', 's')
            ->leftJoin('s.round', 'r')
            ->leftJoin('m.player1', 'p1')
            ->leftJoin('m.player2', 'p2')
            ->addSelect('s', 'r', 'p1', 'p2')
            ->where('m.tournament = :t')
            ->andWhere('m.status IN (:statuses)')
            ->setParameter('t', $tournament)
            ->setParameter('statuses', [MatchStatus::IN_PROGRESS, MatchStatus::PENDING])
            ->orderBy('m.boardNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
