<?php

namespace App\Entity;

use App\Repository\RideParticipantRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RideParticipantRepository::class)]
#[ORM\Table(name: 'ride_participants')]
#[ORM\UniqueConstraint(name: 'uniq_ride_user', columns: ['ride_id','user_id'])]
#[ORM\Index(columns: ['status'], name: 'idx_rp_status')]
class RideParticipant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Ride::class, inversedBy: 'rideParticipants')]
    #[ORM\JoinColumn(name: 'ride_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Ride $ride = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'rideParticipants')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: 'integer')]
    private ?int $seatsBooked = null;

    #[ORM\Column(type: 'integer')]
    private ?int $creditsUsed = null;

    #[ORM\Column(length: 20)]
    private ?string $status = 'requested';

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $requestedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(length: 20, options: ['default' => 'pending'])]
    private string $feedbackStatus = 'pending';

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $feedbackAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $feedbackNote = null;

    public function __construct()
    {
        $this->requestedAt = new \DateTimeImmutable('now');
        $this->status = 'requested';
        $this->feedbackStatus = 'pending';
    }

    public function getId(): ?int { return $this->id; }

    public function getRide(): ?Ride { return $this->ride; }
    public function setRide(?Ride $ride): self { $this->ride = $ride; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getSeatsBooked(): ?int { return $this->seatsBooked; }
    public function setSeatsBooked(int $seatsBooked): self { $this->seatsBooked = $seatsBooked; return $this; }

    public function getCreditsUsed(): ?int { return $this->creditsUsed; }
    public function setCreditsUsed(int $creditsUsed): self { $this->creditsUsed = $creditsUsed; return $this; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getRequestedAt(): ?\DateTimeImmutable { return $this->requestedAt; }
    public function setRequestedAt(\DateTimeImmutable $requestedAt): self { $this->requestedAt = $requestedAt; return $this; }

    public function getConfirmedAt(): ?\DateTimeImmutable { return $this->confirmedAt; }
    public function setConfirmedAt(?\DateTimeImmutable $confirmedAt): self { $this->confirmedAt = $confirmedAt; return $this; }

    public function getCancelledAt(): ?\DateTimeImmutable { return $this->cancelledAt; }
    public function setCancelledAt(?\DateTimeImmutable $cancelledAt): self { $this->cancelledAt = $cancelledAt; return $this; }

    public function getFeedbackStatus(): string { return $this->feedbackStatus; }
    public function setFeedbackStatus(string $status): self
    {
        $allowed = ['pending', 'ok', 'issue'];
        $this->feedbackStatus = in_array($status, $allowed, true) ? $status : 'pending';
        return $this;
    }

    public function getFeedbackAt(): ?\DateTimeImmutable { return $this->feedbackAt; }
    public function setFeedbackAt(?\DateTimeImmutable $feedbackAt): self { $this->feedbackAt = $feedbackAt; return $this; }

    public function getFeedbackNote(): ?string { return $this->feedbackNote; }
    public function setFeedbackNote(?string $note): self
    {
        $this->feedbackNote = $note !== null && trim($note) === '' ? null : $note;
        return $this;
    }
}
