<?php

namespace App\Entity;

use App\Repository\CreditLedgerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CreditLedgerRepository::class)]
#[ORM\Table(name: 'credit_ledger')]
#[ORM\Index(columns: ['user_id', 'created_at'], name: 'idx_ledger_user_date')]
class CreditLedger
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'creditLedgers')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: 'integer')]
    private ?int $delta = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $source = null;

    #[ORM\ManyToOne(targetEntity: Ride::class, inversedBy: 'creditLedgers')]
    #[ORM\JoinColumn(name: 'ride_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Ride $ride = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable('now');
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getDelta(): ?int { return $this->delta; }
    public function setDelta(int $delta): self { $this->delta = $delta; return $this; }

    public function getSource(): ?string { return $this->source; }
    public function setSource(string $source): self { $this->source = $source; return $this; }

    public function getRide(): ?Ride { return $this->ride; }
    public function setRide(?Ride $ride): self { $this->ride = $ride; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }
}
