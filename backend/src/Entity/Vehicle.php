<?php

namespace App\Entity;

use App\Repository\VehicleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VehicleRepository::class)]
#[ORM\Table(name: 'vehicles')]
#[ORM\UniqueConstraint(name: 'uniq_vehicles_plate', columns: ['plate'])]
#[ORM\Index(columns: ['owner_id'], name: 'idx_vehicles_owner')]
class Vehicle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\ManyToOne(targetEntity: Brand::class, inversedBy: 'vehicles')]
    #[ORM\JoinColumn(name: 'brand_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private ?Brand $brand = null;

    // Plaque: unique & non nullable
    #[ORM\Column(type: 'string', length: 20)]
    private ?string $plate = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $model = null;

    // essence | diesel | electrique | hybride...
    #[ORM\Column(type: 'string', length: 30)]
    private ?string $energy = null;

    // nombre de places totales (unsigned)
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private ?int $seatsTotal = null;

    #[ORM\Column(type: 'string', length: 30, nullable: true)]
    private ?string $color = null;

    // vrai si véhicule "écologique" (électrique)
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private ?bool $eco = false;

    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $firstRegistrationAt = null;

    public function getId(): ?int { return $this->id; }

    public function getOwner(): ?User { return $this->owner; }
    public function setOwner(?User $owner): self { $this->owner = $owner; return $this; }

    public function getBrand(): ?Brand { return $this->brand; }
    public function setBrand(?Brand $brand): self { $this->brand = $brand; return $this; }

    public function getPlate(): ?string { return $this->plate; }
    public function setPlate(string $plate): self { $this->plate = $plate; return $this; }

    public function getModel(): ?string { return $this->model; }
    public function setModel(string $model): self { $this->model = $model; return $this; }

    public function getEnergy(): ?string { return $this->energy; }
    public function setEnergy(string $energy): self { $this->energy = $energy; return $this; }

    public function getSeatsTotal(): ?int { return $this->seatsTotal; }
    public function setSeatsTotal(int $seatsTotal): self { $this->seatsTotal = $seatsTotal; return $this; }

    public function getColor(): ?string { return $this->color; }
    public function setColor(?string $color): self { $this->color = $color; return $this; }

    public function isEco(): ?bool { return $this->eco; }
    public function setEco(bool $eco): self { $this->eco = $eco; return $this; }

    public function getFirstRegistrationAt(): ?\DateTimeImmutable { return $this->firstRegistrationAt; }
    public function setFirstRegistrationAt(\DateTimeImmutable $firstRegistrationAt): self
    {
        $this->firstRegistrationAt = $firstRegistrationAt;
        return $this;
    }
}
