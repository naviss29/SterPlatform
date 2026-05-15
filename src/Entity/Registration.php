<?php

namespace App\Entity;

use App\Enum\RegistrationStatus;
use App\Repository\RegistrationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: RegistrationRepository::class)]
#[ORM\Table(name: 'registrations')]
class Registration
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Tournament::class, inversedBy: 'registrations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tournament $tournament;

    #[ORM\Column(length: 255)]
    private string $playerName;

    #[ORM\Column(length: 255)]
    private string $playerEmail;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $playerPhone = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $playerNames = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripePaymentIntentId = null;

    #[ORM\Column(length: 20, enumType: RegistrationStatus::class)]
    private RegistrationStatus $status = RegistrationStatus::PENDING;

    #[ORM\Column(length: 64, unique: true)]
    private string $qrCodeToken;

    #[ORM\Column(nullable: true)]
    private ?int $platformFeeCents = null;

    #[ORM\Column]
    private bool $feeCollected = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->qrCodeToken = bin2hex(random_bytes(16));
    }

    public function getId(): ?Uuid { return $this->id; }

    public function getTournament(): Tournament { return $this->tournament; }
    public function setTournament(Tournament $tournament): static { $this->tournament = $tournament; return $this; }

    public function getPlayerName(): string { return $this->playerName; }
    public function setPlayerName(string $playerName): static { $this->playerName = $playerName; return $this; }

    public function getPlayerEmail(): string { return $this->playerEmail; }
    public function setPlayerEmail(string $playerEmail): static { $this->playerEmail = $playerEmail; return $this; }

    public function getPlayerPhone(): ?string { return $this->playerPhone; }
    public function setPlayerPhone(?string $playerPhone): static { $this->playerPhone = $playerPhone; return $this; }

    public function getPlayerNames(): ?array { return $this->playerNames; }
    public function setPlayerNames(?array $playerNames): static { $this->playerNames = $playerNames; return $this; }

    public function getStripePaymentIntentId(): ?string { return $this->stripePaymentIntentId; }
    public function setStripePaymentIntentId(?string $id): static { $this->stripePaymentIntentId = $id; return $this; }

    public function getStatus(): RegistrationStatus { return $this->status; }
    public function setStatus(RegistrationStatus $status): static { $this->status = $status; return $this; }

    public function getQrCodeToken(): string { return $this->qrCodeToken; }
    public function setQrCodeToken(string $qrCodeToken): static { $this->qrCodeToken = $qrCodeToken; return $this; }

    public function getPlatformFeeCents(): ?int { return $this->platformFeeCents; }
    public function setPlatformFeeCents(?int $platformFeeCents): static { $this->platformFeeCents = $platformFeeCents; return $this; }

    public function isFeeCollected(): bool { return $this->feeCollected; }
    public function setFeeCollected(bool $feeCollected): static { $this->feeCollected = $feeCollected; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getOrganization(): Organization
    {
        return $this->tournament->getOrganization();
    }
}
