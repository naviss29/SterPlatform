<?php

namespace App\Entity;

use App\Repository\PoolPlayerRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PoolPlayerRepository::class)]
#[ORM\Table(name: 'pool_players')]
#[ORM\UniqueConstraint(columns: ['pool_id', 'registration_id'])]
class PoolPlayer
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Pool::class, inversedBy: 'players')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Pool $pool;

    #[ORM\ManyToOne(targetEntity: Registration::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Registration $registration;

    #[ORM\Column(nullable: true)]
    private ?int $rank = null;

    public function getId(): ?Uuid { return $this->id; }

    public function getPool(): Pool { return $this->pool; }
    public function setPool(Pool $pool): static { $this->pool = $pool; return $this; }

    public function getRegistration(): Registration { return $this->registration; }
    public function setRegistration(Registration $registration): static { $this->registration = $registration; return $this; }

    public function getRank(): ?int { return $this->rank; }
    public function setRank(?int $rank): static { $this->rank = $rank; return $this; }
}
