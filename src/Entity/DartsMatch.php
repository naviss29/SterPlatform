<?php

namespace App\Entity;

use App\Enum\MatchStatus;
use App\Repository\DartsMatchRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DartsMatchRepository::class)]
#[ORM\Table(name: 'matches')]
class DartsMatch
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Tournament::class, inversedBy: 'matches')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tournament $tournament;

    #[ORM\ManyToOne(targetEntity: Pool::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Pool $pool = null;

    #[ORM\Column(nullable: true)]
    private ?int $bracketRound = null;

    #[ORM\Column(nullable: true)]
    private ?int $bracketPosition = null;

    #[ORM\Column]
    private int $boardNumber;

    #[ORM\Column(length: 20, enumType: MatchStatus::class)]
    private MatchStatus $status = MatchStatus::PENDING;

    #[ORM\ManyToOne(targetEntity: Registration::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Registration $player1;

    #[ORM\ManyToOne(targetEntity: Registration::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Registration $player2;

    #[ORM\ManyToOne(targetEntity: Registration::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Registration $winner = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(targetEntity: MatchSet::class, mappedBy: 'match', cascade: ['persist', 'remove'])]
    private Collection $sets;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->sets = new ArrayCollection();
    }

    public function getId(): ?Uuid { return $this->id; }

    public function getTournament(): Tournament { return $this->tournament; }
    public function setTournament(Tournament $tournament): static { $this->tournament = $tournament; return $this; }

    public function getPool(): ?Pool { return $this->pool; }
    public function setPool(?Pool $pool): static { $this->pool = $pool; return $this; }

    public function getBracketRound(): ?int { return $this->bracketRound; }
    public function setBracketRound(?int $bracketRound): static { $this->bracketRound = $bracketRound; return $this; }

    public function getBracketPosition(): ?int { return $this->bracketPosition; }
    public function setBracketPosition(?int $bracketPosition): static { $this->bracketPosition = $bracketPosition; return $this; }

    public function getBoardNumber(): int { return $this->boardNumber; }
    public function setBoardNumber(int $boardNumber): static { $this->boardNumber = $boardNumber; return $this; }

    public function getStatus(): MatchStatus { return $this->status; }
    public function setStatus(MatchStatus $status): static { $this->status = $status; return $this; }

    public function getPlayer1(): Registration { return $this->player1; }
    public function setPlayer1(Registration $player1): static { $this->player1 = $player1; return $this; }

    public function getPlayer2(): Registration { return $this->player2; }
    public function setPlayer2(Registration $player2): static { $this->player2 = $player2; return $this; }

    public function getWinner(): ?Registration { return $this->winner; }
    public function setWinner(?Registration $winner): static { $this->winner = $winner; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getSets(): Collection { return $this->sets; }

    public function getOrganization(): Organization
    {
        return $this->tournament->getOrganization();
    }
}
