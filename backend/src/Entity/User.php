<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    /**
     * @var string The hashed password
     */
    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    private ?string $pseudo = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $creditsBalance = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    /** Rôles de sécurité (ex: ["ROLE_USER","ROLE_ADMIN"]) */
    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $driverPreferences = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $suspendedAt = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $suspensionReason = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $profilePhoto = null;

    /** @var Collection<int, Vehicle> */
    #[ORM\OneToMany(mappedBy: 'owner', targetEntity: Vehicle::class)]
    private Collection $vehicles;

    /** @var Collection<int, Ride> */
    #[ORM\OneToMany(mappedBy: 'driver', targetEntity: Ride::class)]
    private Collection $rides;

    /** @var Collection<int, RideParticipant> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: RideParticipant::class)]
    private Collection $rideParticipants;

    /** @var Collection<int, Review> */
    #[ORM\OneToMany(mappedBy: 'author', targetEntity: Review::class)]
    private Collection $reviews;

    /** @var Collection<int, CreditLedger> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: CreditLedger::class)]
    private Collection $creditLedgers;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable('now');
        $this->driverPreferences = [];
        $this->vehicles = new ArrayCollection();
        $this->rides = new ArrayCollection();
        $this->rideParticipants = new ArrayCollection();
        $this->reviews = new ArrayCollection();
        $this->creditLedgers = new ArrayCollection();
        $this->roles = ['ROLE_USER'];
    }

    public function getId(): ?int { return $this->id; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }

    /** Symfony 6/7 — identifiant unique de connexion */
    public function getUserIdentifier(): string { return (string) $this->email; }

    /** Compat v<6 (non utilisé, mais exigé par l’interface UserInterface dans certains contextes) */
    public function getUsername(): string { return $this->getUserIdentifier(); }

    public function getPassword(): ?string { return $this->password; }
    public function setPassword(string $hashed): self { $this->password = $hashed; return $this; }

    public function getPseudo(): ?string { return $this->pseudo; }
    public function setPseudo(string $pseudo): self { $this->pseudo = $pseudo; return $this; }

    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): self { $this->phone = $phone; return $this; }

    public function getCreditsBalance(): int { return $this->creditsBalance; }
    public function setCreditsBalance(int $creditsBalance): self { $this->creditsBalance = $creditsBalance; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }

    /** @return array<string> */
    public function getRoles(): array
    {
        $roles = $this->roles ?? [];
        if (!in_array('ROLE_USER', $roles, true)) {
            $roles[] = 'ROLE_USER';
        }
        return array_values(array_unique($roles));
    }
    public function setRoles(array $roles): self { $this->roles = $roles; return $this; }

    public function getDriverPreferences(): array
    {
        return $this->driverPreferences ?? [];
    }

    public function setDriverPreferences(?array $preferences): self
    {
        $this->driverPreferences = $preferences ? array_filter($preferences, static fn($v) => $v !== null) : [];
        return $this;
    }

    public function getSuspendedAt(): ?\DateTimeImmutable
    {
        return $this->suspendedAt;
    }

    public function setSuspendedAt(?\DateTimeImmutable $suspendedAt): self
    {
        $this->suspendedAt = $suspendedAt;
        return $this;
    }

    public function getSuspensionReason(): ?string
    {
        return $this->suspensionReason;
    }

    public function setSuspensionReason(?string $reason): self
    {
        $this->suspensionReason = $reason;
        return $this;
    }

    public function getProfilePhoto(): ?string
    {
        return $this->profilePhoto;
    }

    public function setProfilePhoto(?string $profilePhoto): self
    {
        $this->profilePhoto = $profilePhoto;
        return $this;
    }

    public function eraseCredentials(): void {}

    /** @return Collection<int, Vehicle> */
    public function getVehicles(): Collection { return $this->vehicles; }
    public function addVehicle(Vehicle $v): self { if(!$this->vehicles->contains($v)){ $this->vehicles->add($v); $v->setOwner($this);} return $this; }
    public function removeVehicle(Vehicle $v): self { if($this->vehicles->removeElement($v) && $v->getOwner() === $this){ $v->setOwner(null);} return $this; }

    /** @return Collection<int, Ride> */
    public function getRides(): Collection { return $this->rides; }
    public function addRide(Ride $r): self { if(!$this->rides->contains($r)){ $this->rides->add($r); $r->setDriver($this);} return $this; }
    public function removeRide(Ride $r): self { if($this->rides->removeElement($r) && $r->getDriver() === $this){ $r->setDriver(null);} return $this; }

    /** @return Collection<int, RideParticipant> */
    public function getRideParticipants(): Collection { return $this->rideParticipants; }
    public function addRideParticipant(RideParticipant $rp): self { if(!$this->rideParticipants->contains($rp)){ $this->rideParticipants->add($rp); $rp->setUser($this);} return $this; }
    public function removeRideParticipant(RideParticipant $rp): self { if($this->rideParticipants->removeElement($rp) && $rp->getUser() === $this){ $rp->setUser(null);} return $this; }

    /** @return Collection<int, Review> */
    public function getReviews(): Collection { return $this->reviews; }
    public function addReview(Review $rev): self { if(!$this->reviews->contains($rev)){ $this->reviews->add($rev); $rev->setAuthor($this);} return $this; }
    public function removeReview(Review $rev): self { if($this->reviews->removeElement($rev) && $rev->getAuthor() === $this){ $rev->setAuthor(null);} return $this; }

    /** @return Collection<int, CreditLedger> */
    public function getCreditLedgers(): Collection { return $this->creditLedgers; }
    public function addCreditLedger(CreditLedger $cl): self { if(!$this->creditLedgers->contains($cl)){ $this->creditLedgers->add($cl); $cl->setUser($this);} return $this; }
    public function removeCreditLedger(CreditLedger $cl): self { if($this->creditLedgers->removeElement($cl) && $cl->getUser() === $this){ $cl->setUser(null);} return $this; }
}
