<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

#[MongoDB\Document]
class SearchLog
{
    #[MongoDB\Id]
    private ?string $id = null;

    #[MongoDB\Field(type: 'string')]
    private string $from;

    #[MongoDB\Field(type: 'string')]
    private string $to;

    // On stocke la date de recherche telle quelle (YYYY-MM-DD) pour simplicité
    #[MongoDB\Field(type: 'string')]
    private string $date;

    #[MongoDB\Field(type: 'int')]
    private int $resultCount = 0;

    #[MongoDB\Field(type: 'string', nullable: true)]
    private ?string $userId = null;

    #[MongoDB\Field(type: 'string', nullable: true)]
    private ?string $clientIp = null;

    #[MongoDB\Field(type: 'string', nullable: true)]
    private ?string $userAgent = null;

    #[MongoDB\Field(type: 'date_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable('now');
    }

    // Getters (minima utiles)
    public function getId(): ?string { return $this->id; }
    public function getFrom(): string { return $this->from; }
    public function getTo(): string { return $this->to; }
    public function getDate(): string { return $this->date; }
    public function getResultCount(): int { return $this->resultCount; }
    public function getUserId(): ?string { return $this->userId; }
    public function getClientIp(): ?string { return $this->clientIp; }
    public function getUserAgent(): ?string { return $this->userAgent; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    // Setters fluides
    public function setFrom(string $v): self { $this->from = $v; return $this; }
    public function setTo(string $v): self { $this->to = $v; return $this; }
    public function setDate(string $v): self { $this->date = $v; return $this; }
    public function setResultCount(int $v): self { $this->resultCount = $v; return $this; }
    public function setUserId(?string $v): self { $this->userId = $v; return $this; }
    public function setClientIp(?string $v): self { $this->clientIp = $v; return $this; }
    public function setUserAgent(?string $v): self { $this->userAgent = $v; return $this; }
}
