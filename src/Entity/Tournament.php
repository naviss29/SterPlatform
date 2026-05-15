<?php

namespace App\Entity;

use App\Enum\RegistrationMode;
use App\Enum\ScoringMode;
use App\Enum\TournamentStatus;
use App\Repository\TournamentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: TournamentRepository::class)]
#[ORM\Table(name: 'tournaments')]
class Tournament
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Organization $organization;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $date;

    #[ORM\Column(length: 255)]
    private string $location;

    #[ORM\Column(length: 20, enumType: TournamentStatus::class)]
    private TournamentStatus $status = TournamentStatus::DRAFT;

    #[ORM\Column]
    private int $maxPlayers;

    #[ORM\Column]
    private int $entryFee = 0;

    #[ORM\Column]
    private int $nbPools = 1;

    #[ORM\Column]
    private int $nbBoards = 1;

    #[ORM\Column]
    private int $playersPerTeam = 1;

    #[ORM\Column(nullable: true)]
    private ?int $advancementPerPool = null;

    #[ORM\Column(length: 20, enumType: RegistrationMode::class)]
    private RegistrationMode $registrationMode = RegistrationMode::ONLINE;

    #[ORM\Column(length: 20, enumType: ScoringMode::class)]
    private ScoringMode $scoringMode = ScoringMode::ELECTRONIC;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(targetEntity: Round::class, mappedBy: 'tournament', cascade: ['persist', 'remove'])]
    private Collection $rounds;

    #[ORM\OneToMany(targetEntity: Registration::class, mappedBy: 'tournament', cascade: ['persist', 'remove'])]
    private Collection $registrations;

    #[ORM\OneToMany(targetEntity: Pool::class, mappedBy: 'tournament', cascade: ['persist', 'remove'])]
    private Collection $pools;

    #[ORM\OneToMany(targetEntity: DartsMatch::class, mappedBy: 'tournament', cascade: ['persist', 'remove'])]
    private Collection $matches;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->rounds = new ArrayCollection();
        $this->registrations = new ArrayCollection();
        $this->pools = new ArrayCollection();
        $this->matches = new ArrayCollection();
    }

    public function getId(): ?Uuid { return $this->id; }

    public function getOrganization(): Organization { return $this->organization; }
    public function setOrganization(Organization $organization): static { $this->organization = $organization; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getDate(): \DateTimeImmutable { return $this->date; }
    public function setDate(\DateTimeImmutable $date): static { $this->date = $date; return $this; }

    public function getLocation(): string { return $this->location; }
    public function setLocation(string $location): static { $this->location = $location; return $this; }

    public function getStatus(): TournamentStatus { return $this->status; }
    public function setStatus(TournamentStatus $status): static { $this->status = $status; return $this; }

    public function getMaxPlayers(): int { return $this->maxPlayers; }
    public function setMaxPlayers(int $maxPlayers): static { $this->maxPlayers = $maxPlayers; return $this; }

    public function getEntryFee(): int { return $this->entryFee; }
    public function setEntryFee(int $entryFee): static { $this->entryFee = $entryFee; return $this; }

    public function getNbPools(): int { return $this->nbPools; }
    public function setNbPools(int $nbPools): static { $this->nbPools = $nbPools; return $this; }

    public function getNbBoards(): int { return $this->nbBoards; }
    public function setNbBoards(int $nbBoards): static { $this->nbBoards = $nbBoards; return $this; }

    public function getPlayersPerTeam(): int { return $this->playersPerTeam; }
    public function setPlayersPerTeam(int $playersPerTeam): static { $this->playersPerTeam = $playersPerTeam; return $this; }

    public function getAdvancementPerPool(): ?int { return $this->advancementPerPool; }
    public function setAdvancementPerPool(?int $advancementPerPool): static { $this->advancementPerPool = $advancementPerPool; return $this; }

    public function getRegistrationMode(): RegistrationMode { return $this->registrationMode; }
    public function setRegistrationMode(RegistrationMode $registrationMode): static { $this->registrationMode = $registrationMode; return $this; }

    public function getScoringMode(): ScoringMode { return $this->scoringMode; }
    public function setScoringMode(ScoringMode $scoringMode): static { $this->scoringMode = $scoringMode; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getRounds(): Collection { return $this->rounds; }
    public function getRegistrations(): Collection { return $this->registrations; }
    public function getPools(): Collection { return $this->pools; }
    public function getMatches(): Collection { return $this->matches; }
}
