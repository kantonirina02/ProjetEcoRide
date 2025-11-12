<?php

namespace App\Entity;

use App\Repository\RideRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RideRepository::class)]
#[ORM\Table(name: 'rides')]
#[ORM\Index(columns: ['from_city', 'to_city', 'start_at'], name: 'idx_rides_from_to_start')]
class Ride
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'rides')]
    #[ORM\JoinColumn(name: 'driver_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private ?User $driver = null;

    #[ORM\ManyToOne(targetEntity: Vehicle::class, inversedBy: 'rides')]
    #[ORM\JoinColumn(name: 'vehicle_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private ?Vehicle $vehicle = null;

    #[ORM\Column(length: 120)]
    private ?string $fromCity = null;

    #[ORM\Column(length: 120)]
    private ?string $toCity = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $startAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $endAt = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private ?string $price = null;

    #[ORM\Column(type: 'integer')]
    private ?int $seatsTotal = null;

    #[ORM\Column(type: 'integer')]
    private ?int $seatsLeft = null;

    #[ORM\Column(type: 'string', length: 20)]
    private ?string $status = 'open';

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private ?bool $allowSmoker = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private ?bool $allowAnimals = false;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $musicStyle = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $payoutReleasedAt = null;

    /** @var Collection<int, RideParticipant> */
    #[ORM\OneToMany(targetEntity: RideParticipant::class, mappedBy: 'ride')]
    private Collection $rideParticipants;

    /** @var Collection<int, Review> */
    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'ride')]
    private Collection $reviews;

    /** @var Collection<int, CreditLedger> */
    #[ORM\OneToMany(targetEntity: CreditLedger::class, mappedBy: 'ride')]
    private Collection $creditLedgers;

    public function __construct()
    {
        $this->rideParticipants = new ArrayCollection();
        $this->reviews = new ArrayCollection();
        $this->creditLedgers = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable('now');
        $this->status = 'open';
        $this->allowSmoker = false;
        $this->allowAnimals = false;
    }

    public function getId(): ?int { return $this->id; }

    public function getDriver(): ?User { return $this->driver; }
    public function setDriver(?User $driver): self { $this->driver = $driver; return $this; }

    public function getVehicle(): ?Vehicle { return $this->vehicle; }
    public function setVehicle(?Vehicle $vehicle): self { $this->vehicle = $vehicle; return $this; }

    public function getFromCity(): ?string { return $this->fromCity; }
    public function setFromCity(string $fromCity): self { $this->fromCity = $fromCity; return $this; }

    public function getToCity(): ?string { return $this->toCity; }
    public function setToCity(string $toCity): self { $this->toCity = $toCity; return $this; }

    public function getStartAt(): ?\DateTimeImmutable { return $this->startAt; }
    public function setStartAt(\DateTimeImmutable $startAt): self { $this->startAt = $startAt; return $this; }

    public function getEndAt(): ?\DateTimeImmutable { return $this->endAt; }
    public function setEndAt(?\DateTimeImmutable $endAt): self { $this->endAt = $endAt; return $this; }

    public function getPrice(): ?string { return $this->price; }
    public function setPrice(string $price): self { $this->price = $price; return $this; }

    public function getSeatsTotal(): ?int { return $this->seatsTotal; }
    public function setSeatsTotal(int $seatsTotal): self { $this->seatsTotal = $seatsTotal; return $this; }

    public function getSeatsLeft(): ?int { return $this->seatsLeft; }
    public function setSeatsLeft(int $seatsLeft): self { $this->seatsLeft = $seatsLeft; return $this; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function isAllowSmoker(): ?bool { return $this->allowSmoker; }
    public function setAllowSmoker(bool $allowSmoker): self { $this->allowSmoker = $allowSmoker; return $this; }

    public function isAllowAnimals(): ?bool { return $this->allowAnimals; }
    public function setAllowAnimals(bool $allowAnimals): self { $this->allowAnimals = $allowAnimals; return $this; }

    public function getMusicStyle(): ?string { return $this->musicStyle; }
    public function setMusicStyle(?string $musicStyle): self { $this->musicStyle = $musicStyle; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }

    public function getPayoutReleasedAt(): ?\DateTimeImmutable { return $this->payoutReleasedAt; }
    public function setPayoutReleasedAt(?\DateTimeImmutable $releasedAt): self { $this->payoutReleasedAt = $releasedAt; return $this; }

    /** @return Collection<int, RideParticipant> */
    public function getRideParticipants(): Collection { return $this->rideParticipants; }
    public function addRideParticipant(RideParticipant $rp): self
    { if(!$this->rideParticipants->contains($rp)){ $this->rideParticipants->add($rp); $rp->setRide($this);} return $this; }
    public function removeRideParticipant(RideParticipant $rp): self
    { if($this->rideParticipants->removeElement($rp) && $rp->getRide() === $this){ $rp->setRide(null);} return $this; }

    /** @return Collection<int, Review> */
    public function getReviews(): Collection { return $this->reviews; }
    public function addReview(Review $review): self
    { if(!$this->reviews->contains($review)){ $this->reviews->add($review); $review->setRide($this);} return $this; }
    public function removeReview(Review $review): self
    { if($this->reviews->removeElement($review) && $review->getRide() === $this){ $review->setRide(null);} return $this; }

    /** @return Collection<int, CreditLedger> */
    public function getCreditLedgers(): Collection { return $this->creditLedgers; }
    public function addCreditLedger(CreditLedger $cl): self
    { if(!$this->creditLedgers->contains($cl)){ $this->creditLedgers->add($cl); $cl->setRide($this);} return $this; }
    public function removeCreditLedger(CreditLedger $cl): self
    { if($this->creditLedgers->removeElement($cl) && $cl->getRide() === $this){ $cl->setRide(null);} return $this; }
}
