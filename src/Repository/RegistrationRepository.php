<?php

namespace App\Repository;

use App\Entity\Registration;
use App\Entity\Tournament;
use App\Enum\RegistrationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Registration>
 */
class RegistrationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Registration::class);
    }

    /** @return Registration[] */
    public function findPaidByTournament(Tournament $tournament): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.tournament = :t')
            ->andWhere('r.status = :status')
            ->setParameter('t', $tournament)
            ->setParameter('status', RegistrationStatus::PAID)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByQrToken(string $token): ?Registration
    {
        return $this->findOneBy(['qrCodeToken' => $token]);
    }
}
