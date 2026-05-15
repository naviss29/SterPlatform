<?php

namespace App\Entity;

use App\Repository\MatchSetRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: MatchSetRepository::class)]
#[ORM\Table(name: 'match_sets')]
#[ORM\UniqueConstraint(columns: ['match_id', 'round_id'])]
class MatchSet
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: DartsMatch::class, inversedBy: 'sets')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private DartsMatch $match;

    #[ORM\ManyToOne(targetEntity: Round::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Round $round;

    #[ORM\ManyToOne(targetEntity: Registration::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Registration $winner = null;

    #[ORM\Column]
    private bool $validatedP1 = false;

    #[ORM\Column]
    private bool $validatedP2 = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid { return $this->id; }

    public function getMatch(): DartsMatch { return $this->match; }
    public function setMatch(DartsMatch $match): static { $this->match = $match; return $this; }

    public function getRound(): Round { return $this->round; }
    public function setRound(Round $round): static { $this->round = $round; return $this; }

    public function getWinner(): ?Registration { return $this->winner; }
    public function setWinner(?Registration $winner): static { $this->winner = $winner; return $this; }

    public function isValidatedP1(): bool { return $this->validatedP1; }
    public function setValidatedP1(bool $validatedP1): static { $this->validatedP1 = $validatedP1; return $this; }

    public function isValidatedP2(): bool { return $this->validatedP2; }
    public function setValidatedP2(bool $validatedP2): static { $this->validatedP2 = $validatedP2; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getOrganization(): Organization
    {
        return $this->match->getOrganization();
    }
}
