<?php

namespace App\Repository;

use App\Entity\Pool;
use App\Entity\PoolPlayer;
use App\Entity\Registration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PoolPlayer>
 */
class PoolPlayerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PoolPlayer::class);
    }

    public function findByPoolAndRegistration(Pool $pool, Registration $registration): ?PoolPlayer
    {
        return $this->findOneBy(['pool' => $pool, 'registration' => $registration]);
    }
}
