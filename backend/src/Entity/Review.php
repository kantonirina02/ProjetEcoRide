<?php

namespace App\Entity;

use App\Repository\ReviewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReviewRepository::class)]
#[ORM\Table(name: 'reviews')]
class Review
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $author = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $target = null;

    #[ORM\ManyToOne(targetEntity: Ride::class, inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Ride $ride = null;

    #[Assert\Range(min: 1, max: 5)]
    #[ORM\Column(type: 'integer')]
    private int $rating = 5;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    // pending | approved | rejected
    #[ORM\Column(length: 20, options: ['default' => 'pending'])]
    private string $status = 'pending';

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $validatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'validated_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $validatedBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $moderationNote = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable('now');
        $this->status = 'pending';
    }

    public function getId(): ?int { return $this->id; }

    public function getAuthor(): ?User { return $this->author; }
    public function setAuthor(User $author): self { $this->author = $author; return $this; }

    public function getTarget(): ?User { return $this->target; }
    public function setTarget(User $target): self { $this->target = $target; return $this; }

    public function getRide(): ?Ride { return $this->ride; }
    public function setRide(?Ride $ride): self { $this->ride = $ride; return $this; }

    public function getRating(): int { return $this->rating; }
    public function setRating(int $rating): self { $this->rating = max(1, min(5, $rating)); return $this; }

    public function getComment(): ?string { return $this->comment; }
    public function setComment(?string $comment): self { $this->comment = ($comment !== null && trim($comment) === '') ? null : $comment; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self
    {
        $allowed = ['pending','approved','rejected'];
        $this->status = in_array($status, $allowed, true) ? $status : 'pending';
        return $this;
    }

    public function getValidatedAt(): ?\DateTimeImmutable { return $this->validatedAt; }
    public function setValidatedAt(?\DateTimeImmutable $validatedAt): self { $this->validatedAt = $validatedAt; return $this; }

    public function getValidatedBy(): ?User { return $this->validatedBy; }
    public function setValidatedBy(?User $validatedBy): self { $this->validatedBy = $validatedBy; return $this; }

    public function getModerationNote(): ?string { return $this->moderationNote; }
    public function setModerationNote(?string $note): self { $this->moderationNote = ($note !== null && trim($note) === '') ? null : $note; return $this; }
}
