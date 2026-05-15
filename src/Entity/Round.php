<?php

namespace App\Entity;

use App\Enum\EntryType;
use App\Enum\FinishType;
use App\Enum\GameType;
use App\Repository\RoundRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: RoundRepository::class)]
#[ORM\Table(name: 'rounds')]
#[ORM\UniqueConstraint(columns: ['tournament_id', 'round_order'])]
class Round
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Tournament::class, inversedBy: 'rounds')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tournament $tournament;

    #[ORM\Column(name: 'round_order')]
    private int $roundOrder;

    #[ORM\Column(length: 10, enumType: GameType::class)]
    private GameType $gameType;

    #[ORM\Column(length: 10, enumType: EntryType::class)]
    private EntryType $entryType;

    #[ORM\Column(length: 10, enumType: FinishType::class)]
    private FinishType $finishType;

    public function getId(): ?Uuid { return $this->id; }

    public function getTournament(): Tournament { return $this->tournament; }
    public function setTournament(Tournament $tournament): static { $this->tournament = $tournament; return $this; }

    public function getRoundOrder(): int { return $this->roundOrder; }
    public function setRoundOrder(int $roundOrder): static { $this->roundOrder = $roundOrder; return $this; }

    public function getGameType(): GameType { return $this->gameType; }
    public function setGameType(GameType $gameType): static { $this->gameType = $gameType; return $this; }

    public function getEntryType(): EntryType { return $this->entryType; }
    public function setEntryType(EntryType $entryType): static { $this->entryType = $entryType; return $this; }

    public function getFinishType(): FinishType { return $this->finishType; }
    public function setFinishType(FinishType $finishType): static { $this->finishType = $finishType; return $this; }
}
